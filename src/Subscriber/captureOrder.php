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
use Shopware\Core\Framework\Uuid\Uuid;
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

    /**
     * @var EntityRepositoryInterface
     */
    private $lightGingerRepository;

    /**
     * @var Helper
     */
    protected $helper;

    public function __construct(
        EntityRepositoryInterface $lightGingerRepository,
        EntityRepositoryInterface $orderRepository,
        EntityRepositoryInterface $orderDeliveryRepository,
        SystemConfigService $systemConfigService,
        Helper $helper
    ) {
        $this->helper = $helper;
        $this->lightGingerRepository = $lightGingerRepository;
        $this->orderRepository = $orderRepository;
        $this->orderDeliveryRepository = $orderDeliveryRepository;
        $EmsPayConfig = $systemConfigService->get('EmsPay.config');
        $this->ginger = $helper->getGignerClinet($EmsPayConfig['emsOnlineApikey'], $EmsPayConfig['emsOnlineBundleCacert']);
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
        $ems_order_id = $this->searchGingerOrderId(current($order->getTransactions()->getIds()),$context);
        if (is_null($ems_order_id)) {
            return;
        }
        try {
            $emsOrder = $this->ginger->getOrder($ems_order_id);
            $transactionId = !empty(current($emsOrder['transactions'])) ? current($emsOrder['transactions'])['id'] : null;
            $this->ginger->captureOrderTransaction($ems_order_id,$transactionId);

        } catch (Exception $exception) {
            $this->helper->saveEMSLog($exception->getMessage(), ['FILE' => __FILE__, 'FUNCTION' => __FUNCTION__, 'LINE' => __LINE__]);
            print_r($exception->getMessage());exit;
        }
        }

    /**
     * Search Ginger order ID in Light Entity Repository
     *
     * @param $sw_order_id
     * @param $context
     * @return mixed
     */

    protected function searchGingerOrderId($sw_order_id,$context){
        $light_entity_search_result = $this->lightGingerRepository->search
        (
            (new Criteria())->addFilter(new EqualsFilter('id', $sw_order_id)),
            $context
        );
        $element = current($light_entity_search_result->getElements());
        return $element ?  $element->getGingerOrderId() : null;
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
