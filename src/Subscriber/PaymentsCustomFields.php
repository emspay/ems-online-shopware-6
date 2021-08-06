<?php


namespace GingerPlugin\Subscriber;

use Dompdf\Exception;
use Ginger\ApiClient;
use GingerPlugin\Exception\CustomPluginException;
use GingerPlugin\Components\Redefiner;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextSwitchEvent;

class PaymentsCustomFields implements EventSubscriberInterface
{
    public $paymentMethodRepository;
    public $client;

    /**
     * paymentsCustomFields constructor.
     * @param EntityRepositoryInterface $paymentMethodRepository
     * @param Redefiner $redefiner
     */
    public function __construct(EntityRepositoryInterface $paymentMethodRepository, Redefiner $redefiner)
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->client = $redefiner->getClient();
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        // Return the events to listen to as array like this:  <event to listen to> => <method to execute>
        return [
            CheckoutConfirmPageLoadedEvent::class => 'updateIdealIssuers',
        ];
    }

    /**
     * @param CheckoutConfirmPageLoadedEvent $event
     */
    public function updateIdealIssuers(CheckoutConfirmPageLoadedEvent $event)
    {
        $idealGateway = $this->getGatewayEntity($event, 'ginger_ideal');

        if (!$idealGateway) {
            return;
        }

        if (!$this->client) {
            return;
        }

        if (!$idealGateway->getActive()) {
            return;
        }

        try {
            $issuers = $this->client->getIdealIssuers();
            $customFields = ['issuers' => $issuers];

            $this->updateCustomFields($event, $idealGateway->getID(), $customFields);
        } catch (\Exception $exception) {
            throw new CustomPluginException($exception->getMessage(), 500, 'GINGER_UPDATE_CUSTOM_FIELDS_ERROR_ERROR');
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
