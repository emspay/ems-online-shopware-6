<?php

namespace GingerPlugin\Subscriber;

use Ginger\ApiClient;
use GingerPlugin\Components\BankConfig;
use GingerPlugin\Components\GingerExceptionHandlerTrait;
use GingerPlugin\Exception\CustomPluginException;
use GingerPlugin\Components\Redefiner;
use Shopware\Core\Checkout\Cart\Exception\OrderTransactionNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RefundOrder implements EventSubscriberInterface
{
    use GingerExceptionHandlerTrait;

    protected $ginger;
    private $orderRepository;
    private $orderPaymentRepository;
    private $orderTransactionRepository;

    public function __construct(
        EntityRepositoryInterface $orderPaymentRepository,
        EntityRepositoryInterface $orderRepository,
        EntityRepositoryInterface $orderTransactionRepository,
        Redefiner                 $redefiner
    )
    {
        $this->orderPaymentRepository = $orderPaymentRepository;
        $this->orderRepository = $orderRepository;
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->ginger = $redefiner->getClient();
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        // Return the events to listen to as array like this:  <event to listen to> => <method to execute>
        return [
            'state_machine.order_transaction.state_changed' => 'onOrderTransactionStateChange',
        ];
    }

    /**
     * @throws OrderTransactionNotFoundException
     * @throws OrderNotFoundException
     */
    public function onOrderTransactionStateChange(StateMachineStateChangeEvent $event): void
    {
        $possible_refund_states = ['refunded','refunded_partially'];

        if ($event->getPreviousState()->getTechnicalName() != 'paid') {
            return;
        }

        if (!in_array($event->getNextState()->getTechnicalName(), $possible_refund_states)) {
            return;
        }

        $transactionId = $event->getTransition()->getEntityId();
        $context = $event->getContext();
        $orderTransactionEntity = $this->orderTransactionRepository->search(
            new Criteria([$transactionId]),
            $context
        )->first();

        $orderId = $orderTransactionEntity->getOrderId();
        $order = $this->getOrder($orderId, $context);
        $payment_method_id = current($order->getTransactions()->getPaymentMethodIds());
        /** @var PaymentMethodEntity|null $payment_methoda * */
        $payment_method = $this->orderPaymentRepository->search(
            new Criteria([$payment_method_id]),
            $context
        )->first();

        $payment_code = explode('_', $payment_method->getCustomFields()['payment_name']);

        if (array_key_first($payment_code) != 'ginger') {
            return;
        }

        $ginger_order_id = $order->getCustomFields()['ginger_order_id'];

        try {
            $gingerOrder = $this->ginger->getOrder($ginger_order_id);

            if ($gingerOrder['status'] != 'completed') {
                return;
            }

            $current_transaction = current($gingerOrder['transactions']);
            $transactionId = $current_transaction['id'] ?? null;

            if (!$transactionId) {
                return;
            }

            $this->ginger->refundOrder($ginger_order_id, array_filter([
                'amount' => $gingerOrder['amount'],
                'description' => sprintf('Refund the order number %s',$gingerOrder['merchant_order_id']),
                'merchant_order_id' => $gingerOrder['merchant_order_id'],
                'order_lines' => $this->checkGingerOrderCapturingStatus($gingerOrder)
            ]));
        } catch (\Exception $exception) {
            $this->handleException($exception,$event);
        }
    }

    private function checkGingerOrderCapturingStatus($ginger_order)
    {
        $transaction = current($ginger_order['transactions']);

        if (!in_array($transaction['payment_method'],['afterpay','klarna-pay-later'])) {
            return null;
        }

        if (!in_array('is_captured',$ginger_order['flags'])) {
            return null;
        }

        return $ginger_order['order_lines'];
    }

    /**
     * Retrieving order for Shopware storage (repository)
     * @throws OrderNotFoundException
     */
    private function getOrder(string $orderId, Context $context): OrderEntity
    {
        $orderCriteria = $this->getOrderCriteria($orderId);
        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($orderCriteria, $context)->first();
        if ($order === null) {
            throw new OrderNotFoundException($orderId);
        }

        return $order;
    }

    private function getOrderCriteria(string $orderId): Criteria
    {
        $orderCriteria = new Criteria([$orderId]);
        $orderCriteria->addAssociation('transactions');
        return $orderCriteria;
    }
}
