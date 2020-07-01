<?php declare(strict_types=1);

namespace Ginger\EmsPay\Service;

use Ginger\EmsPay\Vendor\Helper;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;

class Gateway implements AsynchronousPaymentHandlerInterface
{

    private $helper;

    private $ginger;

    /**
     * @var OrderTransactionStateHandler
     */
    private $transactionStateHandler;

    private $orderService;

    public function __construct(OrderTransactionStateHandler $transactionStateHandler, SystemConfigService $systemConfigService,OrderService $orderService )
    {
        $this->transactionStateHandler = $transactionStateHandler;
        $this->orderService = $orderService;

        $this->helper = new Helper();

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
            print_r($this->processOrder($transaction));
        } catch (\Exception $e) {
            throw new AsyncPaymentProcessException(
                $transaction->getOrderTransaction()->getId(),
                'An error occurred during the communication with external payment gateway' . PHP_EOL . $e->getMessage()
            );
        }

        // Redirect to external gateway
        return new RedirectResponse($redirectUrl);
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

        // Cancelled payment?
        if ($request->query->getBoolean('cancel')) {
        throw new CustomerCanceledAsyncPaymentException(
        $transactionId,
        'Customer canceled the payment on the PayPal page'
        );
        }

        $paymentState = $request->query->getAlpha('status');

        $context = $salesChannelContext->getContext();
        if ($paymentState === 'completed') {
        // Payment completed, set transaction status to "paid"
        $this->transactionStateHandler->pay($transaction->getOrderTransaction()->getId(), $context);
        } else {
        // Payment not completed, set transaction status to "open"
        $this->transactionStateHandler->reopen($transaction->getOrderTransaction()->getId(), $context);
        }

    }

    private function processOrder($transaction): array
    {
        $preOrder = array_filter([
            'amount' => $this->helper->getAmountInCents(),                                // Amount in cents
            'currency' => $this->helper->getCurrencyName(),                                                 // Currency
            'merchant_order_id' => $this->helper->getOrderNumber(),                                         // Merchant Order Id
            'description' => $this->helper->getOrderDescription(),           // Description
            'customer' => $this->helper->getCustomer(),                                                // Customer information
            'payment_info' => [],                                                                           // Payment info
            'order_lines' => $this->helper->getOrderLines(),  // Order Lines
            'transactions' => $this->helper->getTransactions(),                        // Transactions Array
            'return_url' => $this->helper->getReturnUrl($transaction),                                      // Return URL
            'webhook_url' => $this->helper->getWebhookUrl(),  // Webhook URL
            'extra' => ['plugin' => $this->helper->getPluginVersion()],                                     // Extra information
        ]);
    return $preOrder;
    }
}
