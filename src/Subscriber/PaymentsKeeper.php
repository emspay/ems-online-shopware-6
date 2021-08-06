<?php


namespace GingerPlugin\Subscriber;

use GingerPlugin\Exception\CustomPluginException;
use GingerPlugin\Components\Redefiner;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPage;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use GingerPlugin\Components\GingerFeaturesChecksTrait;
use GingerPlugin\Components\GingerPaymentMethodRepositoryWorkerTrait;

class PaymentsKeeper implements EventSubscriberInterface
{
    use GingerFeaturesChecksTrait;
    use GingerPaymentMethodRepositoryWorkerTrait;

    protected $config;
    protected $paymentMethodRepository;
    protected $request;
    protected $countryIsoCode;
    protected $api_availability = null;
    protected $client;
    protected $salesChannelContext;
    protected $ginger_currency_list;
    protected $errors;

    /**
     * PaymentsKeeper constructor.
     * @param Redefiner $redefiner
     * @param EntityRepositoryInterface $paymentMethodRepository
     */

    public function __construct(
        Redefiner $redefiner,
        EntityRepositoryInterface $paymentMethodRepository
    )
    {
        $this->request = Request::createFromGlobals();
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->client = $redefiner->getClient();
        $this->config = $redefiner->getConfig();
        $this->api_availability = null;
        $this->errors = new ErrorCollection();
    }

    public static function getSubscribedEvents(): array
    {
        // Return the events to listen to as array like this:  <event to listen to> => <method to execute>
        return [
            CheckoutConfirmPageLoadedEvent::class => 'onPaymentMethodConfigure',
            AccountEditOrderPageLoadedEvent::class => 'onEditPageLoaded',
        ];
    }

    public function onEditPageLoaded(AccountEditOrderPageLoadedEvent $event)
    {
        $this->onPaymentMethodConfigure($event);
    }

    /**
     * Function which responsible to subscriber to the salesChannel load event
     *
     * @param $event
     * @throws CustomPluginException
     */

    public function onPaymentMethodConfigure($event)
    {
        $this->salesChannelContext = $event->getSalesChannelContext();
        $payment_methods_ids = $this->getPaymentMethodIds($this->salesChannelContext);

        if (empty($payment_methods_ids)) {
            return;
        }

        $this->setCountryIso($event->getPage());

        foreach ($payment_methods_ids as $key => $value) {
            $payment_method = $this->findPaymentMethodById($value, $event->getContext());
            if (!$payment_method || !$payment_method->getId() || !$payment_method->getCustomFields()) {
                continue;
            }

            $ginger_payment_label = explode('_', $payment_method->getCustomFields()['payment_name']);
            if (array_key_first($ginger_payment_label) != 'ginger') {
                continue;
            }

            if (end($ginger_payment_label) == 'ginger') {
                continue;
            }

            switch ($this->api_availability) {
                case true:
                case null:
                    $this->updatePaymentMethodActiveStatus($payment_method, $event->getContext(), $this->checkAvailability($ginger_payment_label));
                    break;
                case false :
                    $this->updatePaymentMethodActiveStatus($payment_method, $event->getContext(), false);
                    break;
            }
        }
        $search_result = $this->findAllActivePaymentMethods($this->salesChannelContext->getSalesChannel()->getPaymentMethodIds(), $event->getContext());
        $payment_methods = $search_result->getEntities()->getElements();
        $page = $event->getPage()->setPaymentMethods(
            new PaymentMethodCollection(($payment_methods))
        );
    }

    /**
     * A function that searches for a all active payment method.
     */
    protected function findAllActivePaymentMethods($payment_method_ids, $context)
    {
        $criteria = new Criteria($payment_method_ids);
        $criteria->addFilter(
            new EqualsFilter('active', true)
        );
        return $this->paymentMethodRepository->search($criteria, $context);
    }

    protected function setCountryIso($page)
    {
        $iso = "";
        if ($page instanceof CheckoutConfirmPage) {
            $iso = current($page->getCart()->getDeliveries()->getAddresses()->getCountries()->getElements())->getIso();
        } elseif ($page instanceof AccountEditOrderPage) {
            $iso = current($page->getOrder()->getDeliveries()->getElements())->getShippingOrderAddress()->getCountry()->getIso();
        }
        $this->countryIsoCode = $iso;
    }
}
