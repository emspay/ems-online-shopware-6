<?php

namespace Ginger\EmsPay\Vendor;

class Helper
{
    /**
     * EMS Online ShopWare plugin version
     */
    const PLUGIN_VERSION = '1.0.0';

    /**
     * Default currency for Order
     */
    const DEFAULT_CURRENCY = 'EUR';

    /**
     *  Default Ginger endpoint
     */

    const GINGER_ENDPOINT = 'https://api.online.emspay.eu';

    /**
     * Constructor of the class which includes ginger-php autoload
     */

    public function __construct(){
        require_once ("vendor/autoload.php");
    }

    /**
     * create a gigner clinet instance
     *
     * @param string $apiKey
     * @param string $product
     * @param boolean $useBundle
     * @return \Ginger\ApiClient
     */
    public function getGignerClinet($apiKey, $useBundle = false)
    {
        return \Ginger\Ginger::createClient(
            self::GINGER_ENDPOINT,
            $apiKey,
            $useBundle ?
                [
                    CURLOPT_CAINFO => self::getCaCertPath()
                ] : []
        );
    }

    /**
     *  function get Cacert.pem path
     */

    protected static function getCaCertPath(){
        return dirname(__FILE__).'/assets/cacert.pem';
    }

    /**
     * Get amount of the order in cents
     *
     * @param $amount
     * @return int
     */

    public function getAmountInCents($order_amount)
    {
        return (int) round ((float) $order_amount * 100);
    }

    public function getCurrencyName(){
        return '';
    }

    public function getOrderNumber(){
        return '';
    }

    public function getOrderDescription(){
        return '';
    }

    protected function getGender($salutation){
        if ($salutation!="")
            return $salutation == 'mr' ? 'male' : 'female';
        else
            return '';
    }

    /**
     * Function creating customer array
     *
     * @param $info
     * @return array
     *
     */
    public function getCustomer($info_order_transaction,$info_sales_channel){
        return array_filter([
            'gender' => $this->getGender($info_order_transaction->getSalutation()->getSalutationKey()),
            'birthdate' => '',
            'address_type' => 'customer',
            'country' => $info_sales_channel->getActiveBillingAddress()->getCountry()->getIso(),
            'email_address' => $info_order_transaction->getEmail(),
            'first_name' => $info_sales_channel->getFirstName(),
            'last_name' => $info_sales_channel->getLastName(),
            'merchant_customer_id' => (string)$info_order_transaction->getCustomerNumber(),
            'phone_numbers' => '',
            'address' => $this->getShippingAddress($info_sales_channel->getActiveShippingAddress()),
            'locale' => '',
            'ip_address' => $info_order_transaction->getRemoteAddress(),
            'additional_addresses' => '',
        ]);
    }

    protected function getShippingAddress($shipping){
        return implode("\n", array_filter(array(
                trim($shipping->getAdditionalAddressLine1()),
                trim($shipping->getAdditionalAddressLine2()),
                trim($shipping->getStreet()),
                trim($shipping->getZipcode()),
                trim($shipping->getCity())
            )));
    }

    public function getOrderLines(){
        return '';
    }

    public function getTransactions(){
        return '';
    }

    public function getWebhookUrl(){
        return '';
    }

    public function getPluginVersion(){
        return '';
    }
}
