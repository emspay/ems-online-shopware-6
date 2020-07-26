<?php


namespace Ginger\EmsPay\Subscriber;

use Ginger\ApiClient;
use Ginger\EmsPay\Service\Helper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextSwitchEvent;

class paymentsCustomFields implements EventSubscriberInterface
{

    /**
     * @var EntityRepositoryInterface
     */

    private $paymentMethodRepository;

    /**
     * @var Helper
     */

    private $helper;

    /**
     * @var ApiClient
     */

    private $ginger;

    /**
     * idealIssuer constructor.
     * @param EntityRepositoryInterface $paymentMethodRepository
     * @param SystemConfigService $systemConfigService
     * @param Helper $helper
     */
    public function __construct(EntityRepositoryInterface $paymentMethodRepository, SystemConfigService $systemConfigService, Helper $helper)
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->helper = $helper;
        $EmsPayConfig = $systemConfigService->get('EmsPay.config');
        $this->ginger = $this->helper->getGignerClinet($EmsPayConfig['emsOnlineApikey'], $EmsPayConfig['emsOnlineBundleCacert']);
    }

    /**
     * @return array|string[]
     */
    public static function getSubscribedEvents(): array
    {
        // Return the events to listen to as array like this:  <event to listen to> => <method to execute>
        return [
            CheckoutConfirmPageLoadedEvent::class => 'updateIdealIssuers',
            SalesChannelContextSwitchEvent::class => 'saveCustomFields',
        ];
    }

    /**
     * @param CheckoutConfirmPageLoadedEvent $event
     */
    public function updateIdealIssuers(CheckoutConfirmPageLoadedEvent $event)
    {
        $idealGateway = $this->getGatewayEntity($event, 'emspay_ideal');
        $customFields = ['issuers' => $this->ginger->getIdealIssuers(), 'issuer_id' => $idealGateway->getCustomFields()['issuer_id']];
        $this->updateCustomFields($event, $idealGateway->getID(), $customFields);
    }

    /**
     * @param SalesChannelContextSwitchEvent $event
     */
    public function saveCustomFields(SalesChannelContextSwitchEvent $event)
    {
        $requestData = $event->getRequestDataBag()->all();
        $idealGateway = $this->getGatewayEntity($event, 'emspay_ideal');
        $afterpayGateway = $this->getGatewayEntity($event, 'emspay_afterpay');

        if($idealGateway->getID() != $requestData['paymentMethodId']) {
            $customFields = ['issuers' => $idealGateway->getCustomFields()['issuers'], 'issuer_id' => ''];
        } else {
            $customFields = ['issuers' => $idealGateway->getCustomFields()['issuers'], 'issuer_id' => $requestData['emspay_issuer_id']];
        }
        $this->updateCustomFields($event, $idealGateway->getID(), $customFields);

        if($afterpayGateway->getID() != $requestData['paymentMethodId']) {
            $customFields = ['emspay_birthday' => null];
        } else {
            $customFields = ['emspay_birthday' => $requestData['emspay_birthday']];
        }

        $this->updateCustomFields($event, $afterpayGateway->getID(), $customFields);
    }

    /**
     * @param $event
     * @return mixed
     */
    protected function getGatewayEntity($event, $gateway_name)
    {
        $gatewayEntyty = $this->paymentMethodRepository->search((new Criteria())->addFilter(new EqualsFilter('description', $gateway_name)), $event->getContext());
        return current(current($gatewayEntyty->getEntities()));
    }

    /**
     * @param $event
     * @param $gatewayID
     * @param $customFields
     * @return \Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent
     */
    protected function updateCustomFields($event, $gatewayID, $customFields)
    {
        return $this->paymentMethodRepository->update([[
            'id' => $gatewayID,
            'customFields' => $customFields

        ]], $event->getContext());
    }
}
