<?php declare(strict_types=1);

namespace Ginger\EmsPay\Service;

use Ginger\ApiClient;
use Ginger\EmsPay\Exception\EmsPluginException;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class Gateway implements AsynchronousPaymentHandlerInterface
{

    /**
     * @var Helper
     */

    private $helper;

    /**
     * @var ApiClient
     */

    private $ginger;

    /**
     * @var OrderTransactionStateHandler
     */
    private $transactionStateHandler;

    /**
     * @var EntityRepositoryInterface
     */

    private $orderRepository;

    /**
     * @var mixed
     */

    private $use_webhook;

    /**
     * @var
     */

    private $clientBuilder;


    /**
     * Gateway constructor.
     * @param EntityRepositoryInterface $orderRepository
     * @param OrderTransactionStateHandler $transactionStateHandler
     * @param ClientBuilder $clientBuilder
     * @param Helper $helper
     */

    public function __construct
    (
        EntityRepositoryInterface $orderRepository,
        OrderTransactionStateHandler $transactionStateHandler,
        ClientBuilder $clientBuilder,
        Helper $helper
    )
    {
        $this->orderRepository = $orderRepository;
        $this->transactionStateHandler = $transactionStateHandler;
        $this->helper = $helper;
        $this->clientBuilder = $clientBuilder;
        $this->use_webhook = $this->clientBuilder->getConfig()['emsOnlineUseWebhook'];
    }

    /**
     * @throws AsyncPaymentProcessException
     */
    public function pay(
        AsyncPaymentTransactionStruct $transaction,
        RequestDataBag $dataBag,
        SalesChannelContext $salesChannelContext
    ): RedirectResponse {

        // Method that sends the return URL to the external gateway and gets a redirect URL back
        try {
            $this->ginger = $this->clientBuilder->getClient($transaction->getOrderTransaction()->getPaymentMethod()->getDescription());
            $pre_order = $this->processOrder($transaction,$salesChannelContext);
            $order = $this->ginger->createOrder($pre_order);

            if($order['status'] == 'error') {
                $this->transactionStateHandler->fail($transaction->getOrderTransaction()->getId(), $salesChannelContext->getContext());
                throw new EmsPluginException(current($order['transactions'])['reason']);
            }
        } catch (\Exception $e) {
            throw new EmsPluginException($e->getMessage());
        }

        // Redirect to external gateway
        return new RedirectResponse(
            isset($order['order_url']) ? $order['order_url'] : current($order['transactions'])['payment_url']
        );
    }

    /**
     * @throws CustomerCanceledAsyncPaymentException
     */
    public function finalize(
        AsyncPaymentTransactionStruct $transaction,
        Request $request,
        SalesChannelContext $salesChannelContext
    ): void {
        $this->ginger = $this->clientBuilder->getClient($salesChannelContext->getPaymentMethod()->getDescription());
        $order = $this->ginger->getOrder($_GET['order_id']);
        $context = $salesChannelContext->getContext();
        $paymentState = $order['status'];
        if (!($this->helper::SHOPWARE_STATES_TO_GINGER[$transaction->getOrderTransaction()->getStateMachineState()->getTechnicalName()] == $paymentState))
            switch ($paymentState) {
            case 'completed' :
                $this->helper->saveGingerOrderId(
                    $transaction->getOrderTransaction()->getId(),
                    $order['id'],
                    $this->orderRepository,
                    $context);
                $this->transactionStateHandler->paid($transaction->getOrderTransaction()->getId(), $context); break;
            case 'cancelled' : $this->transactionStateHandler->cancel($transaction->getOrderTransaction()->getId(), $context); break;
            case 'new' : $this->transactionStateHandler->reopen($transaction->getOrderTransaction()->getId(), $context); break;
            case 'processing' : $this->transactionStateHandler->process($transaction->getOrderTransaction()->getId(), $context); break;
            case 'error' : $this->transactionStateHandler->fail($transaction->getOrderTransaction()->getId(), $context);
               $message ='Error during transaction';
               $message .= isset(current($order['transactions'])['reason']) ? ':'.current($order['transactions'])['reason'].'.' : '.';
               $message .= '<br> Please contact support.';

            throw new EmsPluginException($message);
        }
    }

    private function processOrder($transaction,$sales_channel_context): array
    {
        $issuer_id = $sales_channel_context->getPaymentMethod()->getCustomFields()['issuer_id'] ?? null;
        return array_filter([
            'amount' => $this->helper->getAmountInCents($transaction->getOrder()->getAmountTotal()),                                                     // Amount in cents
            'currency' => $sales_channel_context->getCurrency()->getIsoCode(),                                                                           // Currency
            'merchant_order_id' => $transaction->getOrder()->getOrderNumber(),                                                                           // Merchant Order Id
            'description' => $this->helper->getOrderDescription($transaction->getOrder()->getOrderNumber(),$sales_channel_context->getSalesChannel()),   // Description
            'customer' => $this->helper->getCustomer($sales_channel_context->getCustomer()),                                                             // Customer information
            'order_lines' => $this->helper->getOrderLines($sales_channel_context,$transaction->getOrder()),                                              // Order Lines
            'transactions' => $this->helper->getTransactions($sales_channel_context->getPaymentMethod(), $issuer_id),                                    // Transactions Array
            'return_url' => $transaction->getReturnUrl(),                                                                                                // Return URL
            'webhook_url' => $this->use_webhook ? $this->helper->getWebhookUrl() : null,                                                                                             // Webhook URL
            'extra' => $this->helper->getExtraArray($transaction->getOrderTransaction()->getId()),                                                       // Extra information
            'payment_info' => [],                                                                                                                        // Payment info
        ]);
    }
}
