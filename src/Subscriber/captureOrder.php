<?php

namespace Ginger\EmsPay\Subscriber;

use Ginger\ApiClient;
use Ginger\EmsPay\Service\Helper;
use PHPUnit\Exception;
use Shopware\Core\Checkout\Cart\Exception\OrderDeliveryNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;


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


    public function __construct(
        EntityRepositoryInterface $orderRepository,
        EntityRepositoryInterface $orderDeliveryRepository,
        SystemConfigService $systemConfigService,
        Helper $helper
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderDeliveryRepository = $orderDeliveryRepository;
        $EmsPayConfig = $systemConfigService->get('EmsPay.config');
        $this->ginger = $helper->getClient($EmsPayConfig);
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
        $ems_order_id = $order->getCustomFields()['ems_order_id'];
        if (is_null($ems_order_id)) {
            return;
        }
        try {
            $emsOrder = $this->ginger->getOrder($ems_order_id);
            $transactionId = !empty(current($emsOrder['transactions'])) ? current($emsOrder['transactions'])['id'] : null;
            if (!(current($emsOrder['transactions'])['is_fully_captured']))
            $this->ginger->captureOrderTransaction($ems_order_id,$transactionId);

        } catch (Exception $exception) {
            print_r($exception->getMessage());exit;
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
