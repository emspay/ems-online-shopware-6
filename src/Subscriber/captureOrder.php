<?php

namespace Ginger\EmsPay\Subscriber;

use Shopware\Core\Checkout\Cart\Exception\OrderDeliveryNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;


class captureOrder
{
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
    private $lightGingerRepository;

    public function __construct(
        EntityRepositoryInterface $lightGingerRepository,
        EntityRepositoryInterface $orderRepository,
        EntityRepositoryInterface $orderDeliveryRepository
    ) {
        $this->lightGingerRepository = $lightGingerRepository;
        $this->orderRepository = $orderRepository;
        $this->orderDeliveryRepository = $orderDeliveryRepository;
    }

    /**
     * @throws OrderDeliveryNotFoundException
     * @throws OrderNotFoundException
     */
    public function onOrderDeliveryStateChange(StateMachineStateChangeEvent $event): void
    {
        $light_ginger = $this->lightGingerRepository->getDefinition()->getEntityClass();
        print_r($this->lightGingerRepository);exit;
        $light_ginger->setGingerOrderId('123');

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

        $orderId = $orderDelivery->getOrderId();

        $order = $this->getOrder($orderId,$context);
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
