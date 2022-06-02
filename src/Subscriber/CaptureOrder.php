<?php

namespace GingerPlugin\Subscriber;

use Ginger\ApiClient;
use GingerPlugin\Components\BankConfig;
use GingerPlugin\Components\GingerExceptionHandlerTrait;
use GingerPlugin\Exception\CustomPluginException;
use GingerPlugin\Components\Redefiner;
use Shopware\Core\Checkout\Cart\Exception\OrderDeliveryNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Framework\Log\LoggerFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CaptureOrder implements EventSubscriberInterface
{
    use GingerExceptionHandlerTrait;

    protected $ginger;
    private $orderRepository;
    private $orderDeliveryRepository;
    private $orderPaymentRepository;
    public $loggerFactory;

    public function __construct(
        EntityRepositoryInterface $orderPaymentRepository,
        EntityRepositoryInterface $orderRepository,
        EntityRepositoryInterface $orderDeliveryRepository,
        Redefiner                 $redefiner,
        LoggerFactory             $loggerFactory
    )
    {
        $this->orderPaymentRepository = $orderPaymentRepository;
        $this->orderRepository = $orderRepository;
        $this->orderDeliveryRepository = $orderDeliveryRepository;
        $this->ginger = $redefiner->getClient();
        $this->loggerFactory = $loggerFactory;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        // Return the events to listen to as array like this:  <event to listen to> => <method to execute>
        return [
            'state_machine.order_delivery.state_changed' => 'onOrderDeliveryStateChange',
        ];
    }

    /**
     * @throws OrderDeliveryNotFoundException
     * @throws OrderNotFoundException
     * @throws \Exception
     */
    public function onOrderDeliveryStateChange(StateMachineStateChangeEvent $event)
    {
        $orderDeliveryId = $event->getTransition()->getEntityId();
        $context = $event->getContext();

        /** @var OrderDeliveryEntity|null $orderDelivery */
        $orderDelivery = $this->orderDeliveryRepository->search(
            new Criteria([$orderDeliveryId]),
            $context
        )->first();

        if ($orderDelivery === null) {
            throw new OrderDeliveryNotFoundException($orderDeliveryId);
        }

        if ($orderDelivery->getStateMachineState()->getTechnicalName() != 'shipped') {
            return;
        }

        $orderId = $orderDelivery->getOrderId();
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


        $gingerOrder = $this->ginger->getOrder($ginger_order_id);
        $current_transaction = current($gingerOrder['transactions']);
        $transactionId = ($current_transaction)['id'] ?? null;

        if (!$transactionId) {
            return;
        }

        try {
            if ($current_transaction['is_capturable'] && !in_array('has-captures', $gingerOrder['flags'])) {
                $this->ginger->captureOrderTransaction($ginger_order_id, $transactionId);
            }
        } catch (\Exception $exception) {
            $this->handleException($exception, $event);
        }
    }

    /**
     * Retrieving order for Shopware storage (repository)
     * @throws OrderNotFoundException
     */
    private
    function getOrder(string $orderId, Context $context): OrderEntity
    {
        $orderCriteria = $this->getOrderCriteria($orderId);
        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($orderCriteria, $context)->first();
        if ($order === null) {
            throw new OrderNotFoundException($orderId);
        }

        return $order;
    }

    private
    function getOrderCriteria(string $orderId): Criteria
    {
        $orderCriteria = new Criteria([$orderId]);
        $orderCriteria->addAssociation('transactions');
        return $orderCriteria;
    }
}
