<?php


namespace GingerPlugin\emspay\Subscriber;

use GingerPlugin\emspay\Service\ClientBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

class paymentKeeper implements EventSubscriberInterface
{
    /**
     * @var array|mixed|null
     */

    private $emspayConfig;

    /**
     * @var EntityRepositoryInterface
     */

    private $paymentMethodRepository;

    /**
     * @var Request
     */

    private $request;

    /**
     * @var string
     */

    private $countryIsoCode;

    /**
     * paymentKeeper constructor.
     * @param ClientBuilder $clientBuilder
     * @param EntityRepositoryInterface $paymentMethodRepository
     */

    public function __construct(
        ClientBuilder $clientBuilder,
        EntityRepositoryInterface $paymentMethodRepository
    )
    {
        $this->request = Request::createFromGlobals();
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->emspayConfig = $clientBuilder->getConfig();
    }

    public static function getSubscribedEvents(): array
    {
        // Return the events to listen to as array like this:  <event to listen to> => <method to execute>
        return [
            CheckoutConfirmPageLoadedEvent::class => 'onPaymentMethodConfigure'
        ];
    }

    /**
     * Function which responsible to subscriber to the salesChannel load event
     *
     * @param CheckoutConfirmPageLoadedEvent $event
     */

    public function onPaymentMethodConfigure(CheckoutConfirmPageLoadedEvent $event)
    {
        $salesChannel = $event->getSalesChannelContext()->getSalesChannel();
        $payment_methods_ids = $salesChannel->getPaymentMethodIds();

        if (empty($payment_methods_ids)) {
            return;
        }

        $this->setCountryIso($event->getPage());

        foreach ($payment_methods_ids as $key => $value) {
            $payment_method = $this->findPaymentMethodRepository($value, $event->getContext());
            if ($payment_method == NULL || $payment_method->getId() == NULL || $payment_method->getCustomFields() == NULL) {
                continue;
            }
            $this->updatePaymentMethodRepository($payment_method, $event->getContext());
        }
    }

    /**
     * A function that searches for a payment method repository by its id
     *
     * @param string $paymentMethodId
     * @param $context
     * @return mixed|null
     */

    protected function findPaymentMethodRepository(string $paymentMethodId, $context)
    {
        return $this->paymentMethodRepository->search(new Criteria([$paymentMethodId]), $context)->first();
    }

    /**
     * A function that updates the payment method repository
     *
     * @param $paymentMethod
     * @param $context
     * @return EntityWrittenContainerEvent
     */

    protected function updatePaymentMethodRepository($paymentMethod, $context)
    {
        try {
            return $this->paymentMethodRepository->update(
                [
                    ['id' => $paymentMethod->getId(), 'active' => $this->checkAvailability($paymentMethod->getCustomFields()['payment_name'])],
                ],
                $context
            );
        } catch (\Exception $exception) {
            throw new \GingerPlugin\emspay\Exception\EmsPluginException($exception->getMessage());
        }
    }

    protected function setCountryIso($page)
    {
        $this->countryIsoCode = current($page->getCart()->getDeliveries()->getAddresses()->getCountries()->getElements())->getIso();
    }

    /**
     * A function that matches the user's locale matches with the locales specified in the plugin settings for Afterpay payment method.
     *
     * @return bool
     */

    protected function checkCountryAviability()
    {
        $country_list = array_map('trim', array_filter(explode(',', $this->emspayConfig['emsOnlineAfterPayCountries'])));
        return empty($country_list) || in_array($this->countryIsoCode, $country_list);
    }

    /**
     * A function that matches the user's IP address with the address specified in the plugin settings
     *
     * @param $payment
     * @return bool
     */

    protected function checkAvailability($payment): bool
    {
        $ginger_payment_label = explode('_', $payment);
        if ($ginger_payment_label[0] == 'emspay' && in_array($ginger_payment_label[1], ['klarnapaylater', 'afterpay'])) {
            switch ($ginger_payment_label[1]) {
                case 'afterpay' :
                    if (!$this->checkCountryAviability()) return false;
                    $ip_list = array_map('trim', explode(",", $this->emspayConfig['emsOnlineAfterpayTestIP']));
                    break;
                case 'klarnapaylater' :
                    $ip_list = array_map('trim', explode(",", $this->emspayConfig['emsOnlineKlarnaPayLaterTestIP']));
                    break;
            }
            $ip = $this->request->getClientIp();
            /** @var array $ip_list */
            return empty(array_filter($ip_list)) || in_array($ip, $ip_list);
        }
        return true;
    }
}
