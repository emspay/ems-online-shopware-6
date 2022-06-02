<?php declare(strict_types=1);

namespace GingerPlugin\Service;

use GingerPlugin\Components\BankConfig;
use GingerPlugin\Components\GingerExceptionHandlerTrait;
use GingerPlugin\Exception\CustomPluginException;
use GingerPlugin\Components\Redefiner;
use Ginger\ApiClient;
use http\Exception;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentFinalizeException;
use Shopware\Core\Framework\Log\LoggerFactory;

class FunctionalityGateway extends OrderBuilder implements AsynchronousPaymentHandlerInterface
{
    use GingerExceptionHandlerTrait;

    public $ginger;
    public $transactionStateHandler;
    public $orderRepository;
    public $loggerFactory;

    /**
     * Gateway constructor.
     * @param EntityRepositoryInterface $orderRepository
     * @param OrderTransactionStateHandler $transactionStateHandler
     * @param \Shopware\Core\System\SystemConfig\SystemConfigService $configService
     * @param LoggerFactory $loggerFactory
     */
    public function __construct
    (
        EntityRepositoryInterface    $orderRepository,
        OrderTransactionStateHandler $transactionStateHandler,
        SystemConfigService          $configService,
        LoggerFactory                $loggerFactory
    )
    {
        $this->orderRepository = $orderRepository;
        $this->transactionStateHandler = $transactionStateHandler;
        $this->config = $this->setConfig($configService);
        $this->loggerFactory = $loggerFactory;
    }

    /**
     * @param AsyncPaymentTransactionStruct $transaction
     * @param RequestDataBag $dataBag
     * @param SalesChannelContext $salesChannelContext
     * @return RedirectResponse
     */
    public function pay(
        AsyncPaymentTransactionStruct $transaction,
        RequestDataBag                $dataBag,
        SalesChannelContext           $salesChannelContext
    ): RedirectResponse
    {
        // Method that sends the return URL to the external gateway and gets a redirect URL back
        try {
            $this->setSalesChannelContext($salesChannelContext);
            $this->setPaymentName();
            $this->ginger = $this->getClient($this->getPaymentName());
            $pre_order = $this->processOrder($transaction);
            $order = $this->ginger->createOrder($pre_order);

            $this->saveGingerInformation(
                $transaction->getOrderTransaction()->getId(),
                ['ginger_order_id' => $order['id']],
                $this->orderRepository,
                $salesChannelContext->getContext()
            );

            /**
             * Case, when order created with error, so we can't get the order_id from Ginger anymore.
             */
            if ($order['status'] == 'error') {
                $this->processFailedPayment(
                    new \Exception($order['transactions']['customer_message'], 500),
                    $transaction,
                    $salesChannelContext
                );
            }

            /**
             * Redirect for bank-transfer payment method
             */
            if (isset($order['transactions']) && in_array(current($order['transactions'])['payment_method'], BankConfig::GINGER_REQUIRED_IBAN_INFO_PAYMENTS) && current($order['transactions'])['status']) {
                $order["return_url"] .= "&" . "order_id=" . $order['id'] .
                    "&" . "project_id=" . $order['project_id'];
                return new RedirectResponse($order['return_url']);
            }
        } catch (\Exception $e) {
            $this->processFailedPayment(
                $e,
                $transaction,
                $salesChannelContext
            );
        }

        // Retrieving link to payment page, the order already created successfully.
        try {
            $payment_link = $order['order_url'] ?? current($order['transactions'])['payment_url'];
            // Redirect to payment page
            return new RedirectResponse($payment_link);
        } catch (\Exception $exception) {
            $this->processFailedPayment(
                $exception,
                $transaction,
                $salesChannelContext
            );
        }
        return new RedirectResponse($order['return_url'] . '&' . "order_id=" . $order['id']);
    }

    public function processFailedPayment($e, $transaction, $salesChannelContext)
    {
        $this->transactionStateHandler->fail($transaction->getOrderTransaction()->getId(), $salesChannelContext->getContext());
        $this->handleException($e);
        $this->saveGingerInformation(
            $transaction->getOrderTransaction()->getId(),
            ['error_message' => $e->getMessage()],
            $this->orderRepository,
            $salesChannelContext->getContext()
        );
        throw new AsyncPaymentProcessException(
            (string)$transaction->getOrderTransaction()->getId(),
            $e->getMessage()
        );
    }

    /**
     * @param AsyncPaymentTransactionStruct $transaction
     * @param Request $request
     * @param SalesChannelContext $salesChannelContext
     */
    public function finalize(
        AsyncPaymentTransactionStruct $transaction,
        Request                       $request,
        SalesChannelContext           $salesChannelContext
    ): void
    {
        $this->setSalesChannelContext($salesChannelContext);
        $this->setPaymentName();
        $this->ginger = $this->getClient();
        $order = $this->ginger->getOrder(filter_var($_GET['order_id'], FILTER_SANITIZE_SPECIAL_CHARS));
        $context = $this->sales_channel_context->getContext();
        $paymentState = $order['status'];

        if (isset($order['transactions']) && in_array(current($order['transactions'])['payment_method'], BankConfig::GINGER_REQUIRED_IBAN_INFO_PAYMENTS)) {
            $payment_details = current($order['transactions'])['payment_method_details'];
            $this->saveGingerInformation(
                $transaction->getOrderTransaction()->getId(),
                ['ginger_order_payment_method_details' => $payment_details],
                $this->orderRepository,
                $context
            );
        }

        $transactionId = $transaction->getOrderTransaction()->getId();

        if ($transaction->getOrderTransaction()->getStateMachineState()->getTechnicalName() != 'open') {
            return;
        }

        $current_ginger_order_transaction = current($order['transactions']);

        switch ($paymentState) {
            case 'completed' :
                if (isset(current($order['transactions'])['payment_method_details']['one_click_type']) && current($order['transactions'])['payment_method_details']['one_click_type'] == 'first') {
                    $this->saveGingerInformation(
                        $transaction->getOrderTransaction()->getId(), [
                        'ginger_ghc_details' => [
                            'truncated_pan' => current($order['transactions'])['payment_method_details']['truncated_pan'],
                            'vault_token' => current($order['transactions'])['payment_method_details']['vault_token']
                        ]
                    ],
                        $this->orderRepository,
                        $context
                    );
                }
                $this->transactionStateHandler->paid($transactionId, $context);
                break;
            case 'cancelled' :
                $this->transactionStateHandler->cancel($transactionId, $context);
                throw new CustomerCanceledAsyncPaymentException(
                    $transactionId,
                    // in this case the customer_message may be not present
                    $current_ginger_order_transaction['customer_message'] ?? $current_ginger_order_transaction['reason']);
            case 'new' :
                throw new CustomerCanceledAsyncPaymentException(
                    $transactionId,
                    'Customer cancelled the payment on the ' . $current_ginger_order_transaction['payment_method'] . ' page'
                );
            case 'processing' :
                if ($current_ginger_order_transaction['status'] == 'error') {
                    throw new AsyncPaymentFinalizeException(
                        $transactionId,
                        $current_ginger_order_transaction['customer_message'] ?? $current_ginger_order_transaction['reason']
                    );
                }
                $this->transactionStateHandler->process($transactionId, $context);
                break;
            case 'error' :
                $this->transactionStateHandler->fail($transactionId, $context);
                throw new AsyncPaymentFinalizeException(
                    $transactionId,
                    $current_ginger_order_transaction['customer_message'] ?? $current_ginger_order_transaction['reason']
                );
            default :
                $this->transactionStateHandler->reopen($transaction->getOrderTransaction()->getId(), $context);
                break;
        }
    }

    public function processOrder($transaction): array
    {
        return array_filter([
            'amount' => $this->getAmountInCents($transaction->getOrder()->getAmountTotal()),           // Amount in cents
            'currency' => $this->sales_channel_context->getCurrency()->getIsoCode(),                   // Currency
            'merchant_order_id' => $transaction->getOrder()->getOrderNumber(),                         // Merchant Order Id
            'description' => $this->getOrderDescription($transaction->getOrder()->getOrderNumber()),   // Description
            'customer' => $this->getCustomer($this->sales_channel_context->getCustomer()),             // Customer information
            'order_lines' => $this->getOrderLines($transaction->getOrder()),                           // Order Lines
            'transactions' => $this->getTransactions($this->getIssuerId()),                            // Transactions Array
            'return_url' => $transaction->getReturnUrl(),                                              // Return URL
            'webhook_url' => $this->getWebhookUrl(),                                                   // Webhook URL
            'extra' => $this->getExtraArray($transaction->getOrderTransaction()->getId()),             // Extra information
            'payment_info' => [],                                                                      // Payment info
        ]);
    }
}
