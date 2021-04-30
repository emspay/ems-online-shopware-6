<?php

namespace GingerPlugin\emspay\Subscriber;

use Ginger\ApiClient;
use GingerPlugin\emspay\Exception\EmsPluginException;
use GingerPlugin\emspay\Service\ClientBuilder;
use Shopware\Core\Checkout\Cart\Exception\OrderDeliveryNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;


class captureOrder
{
    /**
     * @var ApiClient
     */

    protected $ginger;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderDeliveryRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderPaymentRepository;

    public function __construct(
        EntityRepositoryInterface $orderPaymentRepository,
        EntityRepositoryInterface $orderRepository,
        EntityRepositoryInterface $orderDeliveryRepository,
        ClientBuilder $clientBuilder
    ) {
        $this->orderPaymentRepository = $orderPaymentRepository;
        $this->orderRepository = $orderRepository;
        $this->orderDeliveryRepository = $orderDeliveryRepository;
        $this->ginger = $clientBuilder->getClient();
    }

    /**
     * @throws OrderDeliveryNotFoundException
     * @throws OrderNotFoundException
     */
    public function onOrderDeliveryStateChange(StateMachineStateChangeEvent $event): void
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
        $order = $this->getOrder($orderId,$context);
        $payment_method_id = current($order->getTransactions()->getPaymentMethodIds());
        /** @var PaymentMethodEntity|null $payment_methoda **/
        $payment_method = $this->orderPaymentRepository->search(
            new Criteria([$payment_method_id]),
            $context
        )->first();

        if (!in_array($payment_method->getCustomFields()['payment_name'],['emspay_klarnapaylater','emspay_afterpay'])) {
            return;
        }

        $ems_order_id = $order->getCustomFields()['ems_order_id'];

        try {
            $emsOrder = $this->ginger->getOrder($ems_order_id);
            $transactionId = !empty(current($emsOrder['transactions'])) ? current($emsOrder['transactions'])['id'] : null;
            if (!(current($emsOrder['transactions'])['is_fully_captured']))
            $this->ginger->captureOrderTransaction($ems_order_id,$transactionId);

        } catch (\Exception $exception) {
            throw new EmsPluginException($exception->getMessage());
        }
        }

    /**
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
