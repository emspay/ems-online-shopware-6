<?php


namespace GingerPlugin\emspay\Subscriber;

use Ginger\ApiClient;
use GingerPlugin\emspay\Service\ClientBuilder;
use GingerPlugin\emspay\Exception\EmsPluginException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
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
     * @var ApiClient
     */

    private $ginger;

    /**
     * paymentsCustomFields constructor.
     * @param EntityRepositoryInterface $paymentMethodRepository
     * @param ClientBuilder $clientBuilder
     */
    public function __construct(EntityRepositoryInterface $paymentMethodRepository, ClientBuilder $clientBuilder)
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->ginger = $clientBuilder->getClient();
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
    public function updateIdealIssuers(CheckoutConfirmPageLoadedEvent $event): void
    {
        $idealGateway = $this->getGatewayEntity($event, 'emspay_ideal');
 //       print_r($idealGateway);exit;
        if (is_null($idealGateway)){
            return;
        }

        $issuers = $this->ginger->getIdealIssuers();
        $customFields = ['issuers' => $issuers, 'issuer_id' => current($issuers)['id']];
        $this->updateCustomFields($event, $idealGateway->getID(), $customFields);
    }

    /**
     * @param SalesChannelContextSwitchEvent $event
     */
    public function saveCustomFields(SalesChannelContextSwitchEvent $event): void
    {
        $requestData = $event->getRequestDataBag()->all();

        // Update Custom fields for iDEAL
        if( ! empty( $idealGateway = $this->getGatewayEntity($event, 'emspay_ideal') ) ) {
            if($idealGateway->getID() != $requestData['paymentMethodId']) {
                $customFields = ['issuers' => $idealGateway->getCustomFields()['issuers'] ?? null, 'issuer_id' => ''];
            } else {
                if(! $requestData['emspay_issuer_id']) {
                    throw new EmsPluginException('In order to place an order, you need to select a bank');
                }
                $customFields = ['issuers' => $idealGateway->getCustomFields()['issuers'], 'issuer_id' => $requestData['emspay_issuer_id']];
            }
            $this->updateCustomFields($event, $idealGateway->getID(), $customFields);
        }

        // Update Custom fields for Afterpay
        if( ! empty( $afterpayGateway = $this->getGatewayEntity($event, 'emspay_afterpay') ) ) {
            if($afterpayGateway->getID() != $requestData['paymentMethodId']) {
                $customFields = ['emspay_birthday' => null];
            } else {
                if(! $requestData['emspay_birthday']) {
                    throw new EmsPluginException('In order to place an order you need to fill in the Birthday field');
                }
                $customFields = ['emspay_birthday' => $requestData['emspay_birthday']];
            }
            $this->updateCustomFields($event, $afterpayGateway->getID(), $customFields);
        }
    }

    /**
     * @param $event
     * @param $gateway_name
     * @return mixed
     */
    protected function getGatewayEntity($event, $gateway_name)
    {
        return $this->paymentMethodRepository->search((new Criteria())->addFilter(new EqualsFilter('customFields.payment_name', $gateway_name)), $event->getContext())->first();
    }

    /**
     * @param $event
     * @param $gatewayID
     * @param $customFields
     * @return \Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent
     */
    protected function updateCustomFields($event, $gatewayID, $customFields): EntityWrittenContainerEvent
    {
        return $this->paymentMethodRepository->update([[
            'id' => $gatewayID,
            'customFields' => $customFields
        ]], $event->getContext());
    }
}
