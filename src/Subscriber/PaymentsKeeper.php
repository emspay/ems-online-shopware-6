<?php


namespace GingerPlugin\Subscriber;

use GingerPlugin\Components\GingerExceptionHandlerTrait;
use GingerPlugin\Components\Redefiner;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use GingerPlugin\Components\BankConfig;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPage;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;

class PaymentsKeeper implements EventSubscriberInterface
{
    use GingerExceptionHandlerTrait;

    protected $config;
    protected $request;
    protected $countryIsoCode;
    protected $client;
    protected $salesChannelContext;
    protected $errors;
    private array $ginger_currency_list;

    /**
     * PaymentsKeeper constructor.
     * @param Redefiner $redefiner
     */

    public function __construct(
        Redefiner $redefiner
    )
    {
        $this->request = Request::createFromGlobals();
        $this->client = $redefiner->getClient();
        $this->config = $redefiner->getConfig();
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
     * Checking availability for three criteria :
     * - Currency
     * - Ip
     * - Country availability
     *
     * @param $event
     */

    public function onPaymentMethodConfigure($event): void
    {
        $availableMethods = $event->getPage()->getPaymentMethods();
        $gingerMethods = $availableMethods->filterAndReduceByProperty('formattedHandlerIdentifier', 'handler_gingerplugin_redefiner')->getElements();

        if (empty($availableMethods)) {
            return;
        }

        $this->salesChannelContext = $event->getSalesChannelContext();
        $this->setCountryIso($event->getPage());

        try {
            $api_status = (bool)$this->client->getIdealIssuers();
        } catch (\Exception $exception) {
            $api_status = false;
            $this->handleException($exception, $event);
        }

        try {
            $this->ginger_currency_list = $this->client->getCurrencyList()['payment_methods'];
        } catch (\Exception $exception) {
            $this->ginger_currency_list = ['EUR'];
            $this->handleException($exception, $event);
        }


        foreach ($gingerMethods as $id => $method) {
            if (!$api_status) {
                continue;
            }

            if (!method_exists($method, 'getCustomFields') || !$method->getCustomFields()) {
                continue;
            }

            $ginger_payment_label = explode('_', $method->getCustomFields()['payment_name']);

            if ($this->checkAvailability(end($ginger_payment_label))) {
                $availableMethods->add($method);
            }
        }
        $event->getPage()->setPaymentMethods(
            $availableMethods
        );
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

    /**
     * A function that matches the user's IP address with the address specified in the plugin settings
     *
     * @param $ginger_payment_label
     * @return bool
     */
    protected function checkAvailability($ginger_payment_label): bool
    {
        if (!$this->checkCurrencyAvailabilityForPaymentMethod($ginger_payment_label)) {
            return false;
        }

        if (!in_array($ginger_payment_label, BankConfig::GINGER_IP_VALIDATION_PAYMENTS)) {
            return true;
        }

        switch ($ginger_payment_label) {
            case 'afterpay' :
                if (!$this->checkCountryAviability()) return false;
                $ip_list = array_map('trim', explode(",", $this->config['GingerAfterpayTestIP']));
                break;
            case 'klarnapaylater' :
                $ip_list = array_map('trim', explode(",", $this->config['GingerKlarnaPayLaterTestIP']));
                break;
        }
        $ip = $this->request->getClientIp();
        /** @var array $ip_list */
        return empty(array_filter($ip_list)) || in_array($ip, $ip_list);
    }

    /**
     * Based on payment currency list return true/false which means that payment method available or not
     * If payment currency list is not provided for requested payment method, use default currency value : EUR
     *
     * @param string $payment
     * @return bool
     */
    protected function checkCurrencyAvailabilityForPaymentMethod($payment): bool
    {
        if (array_key_exists($payment, $this->ginger_currency_list)) {
            $method_currency_list = $this->ginger_currency_list[$payment]['currencies'];
        } else {
            $method_currency_list = ['EUR'];
        }

        $shop_currency = $this->salesChannelContext->getCurrency()->getIsoCode();
        return in_array($shop_currency, $method_currency_list);
    }

    /**
     * A function that matches the user's locale matches with the locales specified in the plugin settings for Afterpay payment method.
     *
     * @return bool
     */
    protected function checkCountryAviability()
    {
        $country_list = array_map('trim', array_filter(explode(',', $this->config['GingerAfterPayCountries'])));
        return empty($country_list) || in_array($this->countryIsoCode, $country_list);
    }
}
