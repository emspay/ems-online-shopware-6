<?php


namespace GingerPlugin\Subscriber;

use GingerPlugin\Components\GingerExceptionHandlerTrait;
use GingerPlugin\Components\Redefiner;
use GingerPlugin\Components\BankConfig;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextSwitchEvent;
use Shopware\Core\Framework\Log\LoggerFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PaymentsCustomFields implements EventSubscriberInterface
{
    use GingerExceptionHandlerTrait;

    public $paymentMethodRepository;
    public $client;
    public $loggerFactory;
    private array $config;

    /**
     * paymentsCustomFields constructor.
     * @param EntityRepositoryInterface $paymentMethodRepository
     * @param Redefiner $redefiner
     * @param LoggerFactory $loggerFactory
     * @param SystemConfigService $systemConfigService
     */
    public function __construct(
        EntityRepositoryInterface $paymentMethodRepository,
        Redefiner                 $redefiner,
        LoggerFactory             $loggerFactory,
        SystemConfigService       $systemConfigService)
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->client = $redefiner->getClient();
        $this->loggerFactory = $loggerFactory;
        $this->config  = $systemConfigService->get(implode('.', [BankConfig::PLUGIN_TECH_PREFIX, 'config']));
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

    public  function updateIdealIssuers(CheckoutConfirmPageLoadedEvent $event)
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
            $this->handleException($exception, $event);
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
