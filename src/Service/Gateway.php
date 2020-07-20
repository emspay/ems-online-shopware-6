<?php declare(strict_types=1);

namespace Ginger\EmsPay\Service;

use Ginger\ApiClient;
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
use Shopware\Core\System\SystemConfig\SystemConfigService;

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

    private $lightGingerRepository;

    /**
     * Gateway constructor.
     * @param OrderTransactionStateHandler $transactionStateHandler
     * @param SystemConfigService $systemConfigService
     * @param Helper $helper
     */

    public function __construct
    (
        EntityRepositoryInterface $lightGingerRepository,
        OrderTransactionStateHandler $transactionStateHandler,
        SystemConfigService $systemConfigService,
        Helper $helper
    )
    {
        $this->lightGingerRepository = $lightGingerRepository;
        $this->transactionStateHandler = $transactionStateHandler;
        $this->helper = $helper;
        $EmsPayConfig = $systemConfigService->get('EmsPay.config');
        $this->ginger = $this->helper->getGignerClinet($EmsPayConfig['emsOnlineApikey'], $EmsPayConfig['emsOnlineBundleCacert']);
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
            $pre_order = $this->processOrder($transaction,$salesChannelContext);
            $order = $this->ginger->createOrder($pre_order);
        } catch (\Exception $e) {
            $this->helper->saveEMSLog($e->getMessage(), ['FILE' => __FILE__, 'FUNCTION' => __FUNCTION__, 'LINE' => __LINE__]);
            print_r($e->getMessage());exit;
            throw new AsyncPaymentProcessException(
                $transaction->getOrderTransaction()->getId(),
                'An error occurred during the creating the EMS Online order' . PHP_EOL . $e->getMessage()
            );
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
        $transactionId = $transaction->getOrderTransaction()->getId();
        $order = $this->ginger->getOrder($_GET['order_id']);
        $context = $salesChannelContext->getContext();
        $paymentState = $order['status'];
        if (!($this->helper::SHOPWARE_STATES_TO_GINGER[$transaction->getOrderTransaction()->getStateMachineState()->getTechnicalName()] == $paymentState))
            switch ($paymentState) {
            case 'completed' :
                $this->helper->saveGingerOrderId(
                    current($order['transactions'])['payment_method'],
                    $transaction->getOrderTransaction()->getId(),
                    $order['id'],
                    $this->lightGingerRepository,
                    $context);
                $this->transactionStateHandler->paid($transaction->getOrderTransaction()->getId(), $context); break;
            case 'cancelled' : $this->transactionStateHandler->cancel($transaction->getOrderTransaction()->getId(), $context); break;
            case 'new' : $this->transactionStateHandler->reopen($transaction->getOrderTransaction()->getId(), $context); break;
            case 'processing' : $this->transactionStateHandler->process($transaction->getOrderTransaction()->getId(), $context); break;
            case 'error' : $this->transactionStateHandler->fail($transaction->getOrderTransaction()->getId(), $context);
               $message ='Error during transaction';
               $message .= isset(current($order['transactions'])['reason']) ? ':'.current($order['transactions'])['reason'].'.' : '.';
               $message .= '<br> Please contact support.';
            $this->helper->saveEMSLog($message, ['FILE' => __FILE__, 'FUNCTION' => __FUNCTION__, 'LINE' => __LINE__]);
            print_r($message);exit;
            throw new CustomerCanceledAsyncPaymentException(
                $transactionId,
                (current($order['transactions'])['reason'])
            ); break;
        }
    }

    private function processOrder($transaction,$sales_channel_context): array
    {
        return array_filter([
            'amount' => $this->helper->getAmountInCents($transaction->getOrder()->getAmountTotal()),                                                     // Amount in cents
            'currency' => $sales_channel_context->getCurrency()->getIsoCode(),                                                                           // Currency
            'merchant_order_id' => $transaction->getOrder()->getOrderNumber(),                                                                           // Merchant Order Id
            'description' => $this->helper->getOrderDescription($transaction->getOrder()->getOrderNumber(),$sales_channel_context->getSalesChannel()),   // Description
            'customer' => $this->helper->getCustomer($sales_channel_context->getCustomer()),                                                             // Customer information
            'order_lines' => $this->helper->getOrderLines($sales_channel_context,$transaction->getOrder()),                          // Order Lines
            'transactions' => $this->helper->getTransactions($sales_channel_context->getPaymentMethod(),$this->ginger->getIdealIssuers()),               // Transactions Array
            'return_url' => $transaction->getReturnUrl(),                                                                                                // Return URL
            'webhook_url' => $this->helper->getWebhookUrl(),                                                                                             // Webhook URL
            'extra' => $this->helper->getExtraArray($transaction->getOrderTransaction()->getId()),                                                       // Extra information
            'payment_info' => [],                                                                                                                        // Payment info
        ]);
    }
}
