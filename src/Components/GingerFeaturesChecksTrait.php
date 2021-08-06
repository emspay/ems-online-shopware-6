<?php

namespace GingerPlugin\Components;

trait GingerFeaturesChecksTrait
{
    /**
     * A function that matches the user's IP address with the address specified in the plugin settings
     *
     * @param $ginger_payment_label
     * @return bool
     */
    protected function checkAvailability($ginger_payment_label): bool
    {
        if (is_bool($this->api_availability) && !$this->api_availability) {
            return false;
        }

        if (is_null($this->api_availability)) {
            $this->api_availability = $this->checkAccessToApi();
        }

        if (!$this->checkCurrencyAvailabilityForPaymentMethod(end($ginger_payment_label))) {
            return false;
        }

        if (in_array(end($ginger_payment_label), BankConfig::GINGER_IP_VALIDATION_PAYMENTS)) {
            switch (end($ginger_payment_label)) {
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
        return true;
    }

    protected function checkCurrencyAvailabilityForPaymentMethod($payment)
    {
        $ginger_payment_label = BankConfig::SHOPWARE_TO_BANK_PAYMENTS[$payment];

        try {
            $ginger_currency_list = $this->client->getCurrencyList();
        } catch (\Exception $exception) {
            return false;
        }

        if (!array_key_exists($ginger_payment_label, $ginger_currency_list['payment_methods'])) {
            return false;
        }

        $available_payment_method_currency = array_values($ginger_currency_list ['payment_methods'][$ginger_payment_label]['currencies']);
        $shop_currency = $this->salesChannelContext->getCurrency()->getIsoCode();
        return in_array($shop_currency, $available_payment_method_currency);
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

    /**
     * Function what checks if the access to api is can be establish using API-Key from config.
     *
     * @return bool
     */
    protected function checkAccessToApi()
    {
        if (!$this->client) {
            return false;
        }

        try {
            $this->client->getIdealIssuers();
        } catch (\Exception $exception) {
            return false;
        }
        return true;
    }

}