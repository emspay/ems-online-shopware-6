<?php

namespace Ginger\EmsPay\Service;

use Ginger\ApiClient;
use Ginger\Ginger;

class Helper
{

    const SHOPWARE_STATES_TO_GINGER =
        [
            'paid' => 'completed',
            'open' => 'new',
            'cancelled' => 'cancelled',
            'processing' => 'processing',
            'pending' => 'pending',
            'failed' => 'error'
        ];

    /**
     *  Translator Shopware 6 Payment Description into payment names for Ginger API
     */
    const SHOPWARE_TO_EMS_PAYMENTS =
        [
            'applepay' => 'apple-pay',
            'klarnapaylater' => 'klarna-pay-later',
            'klarnapaynow' => 'klarna-pay-now',
            'paynow' => null,
            'ideal' => 'ideal',
            'afterpay' => 'afterpay',
            'amex' => 'amex',
            'bancontact' => 'bancontact',
            'banktransfer' => 'bank-transfer',
            'creditcard' => 'credit-card',
            'payconiq' => 'payconiq',
            'paypal' => 'paypal',
            'tikkiepaymentrequest' => 'tikkie-payment-request',
            'wechat' => 'wechat',
        ];

    /**
     * EMS Online ShopWare plugin version
     */
    const PLUGIN_VERSION = '1.0.0';

    /**
     * Default currency for Ginger Orders
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
        include (dirname(__FILE__)."/../Vendor/vendor/autoload.php");
    }

    /**
     * create a gigner clinet instance
     *
     * @param string $apiKey
     * @param boolean $useBundle
     * @return ApiClient
     */
    public function getGignerClinet($apiKey, $useBundle = false)
    {
        return Ginger::createClient(
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
        return dirname(__FILE__).'/../Vendor/assets/cacert.pem';
    }

    /**
     * Get amount of the order in cents
     *
     * @param $order_amount
     * @return int
     */

    public function getAmountInCents($order_amount)
    {
        return (int) round ((float) $order_amount * 100);
    }

    /**
     * Get order description for Payment Provider Gateways
     *
     * @param $number
     * @param $sales_channel
     * @return string
     */

    public function getOrderDescription($number,$sales_channel) {
        $message = 'Your order %s at %s';
        return sprintf($message,(string) $number , $sales_channel->getName());
    }

    /**
     * Function what return a male or female gender for customer array based on Shopware 6 salutation
     *
     * @param $salutation
     * @return string
     */

    protected function getGender($salutation){
        if ($salutation!="")
            return $salutation == 'mr' ? 'male' : 'female';
        else
            return '';
    }

    /**
     * Get birth date from Shopware 6 long date format
     *
     * @param $sales
     * @return mixed|string
     */

    public function getBirthDate($sales){
        return explode(' ',$sales->getBirthday()->date)[0];
    }

    /**
     * Function creating the customer array for the Ginger Order
     *
     * @param $info_sales_channel
     * @return array
     *
     */
    public function getCustomer($info_sales_channel){
        return array_filter([
            'address_type' => 'customer',
            'gender' => $this->getGender($info_sales_channel->getSalutation()->getSalutationKey()),
            'birthdate' => isset($info_sales_channel->getBirthday()->date) ? self::getBirthDate($info_sales_channel) : null,
            'country' => $this->getCountryFromAddress($info_sales_channel->getActiveShippingAddress()),
            'email_address' => $info_sales_channel->getEmail(),
            'first_name' => $info_sales_channel->getFirstName(),
            'last_name' => $info_sales_channel->getLastName(),
            'merchant_customer_id' => (string)$info_sales_channel->getCustomerNumber(),
            'phone_numbers' => [$info_sales_channel->getActiveShippingAddress()->getPhoneNumber()],
            'address' => $this->getAddress($info_sales_channel->getActiveShippingAddress()),
            'locale' => $info_sales_channel->getLanguage() == "" ? 'en' : $info_sales_channel->getLanguage(),
            'ip_address' => $info_sales_channel->getRemoteAddress(),
            'additional_addresses' => $this->getAdditionalAddress($info_sales_channel->getActiveBillingAddress()),
        ]);
    }

    /**
     * Get country from the address array
     *
     * @param $address
     * @return mixed
     */

    protected function getCountryFromAddress($address){
        return $address->getCountry()->getIso();
    }

    /**
     * Get the additional address for the order based on billing address
     *
     * @param $address
     * @return array
     */

    protected function getAdditionalAddress($address){
        return [
            array_filter([
            'address_type' => 'billing',
            'address' => $this->getAddress($address),
            'country' => $this->getCountryFromAddress($address)
            ])
        ];
    }

    /**
     * Get shipping address as default
     *
     * @param $address
     * @return string
     */

    protected function getAddress($address){
        return implode("\n", array_filter(array(
                trim($address->getAdditionalAddressLine1()),
                trim($address->getAdditionalAddressLine2()),
                trim($address->getStreet()),
                trim($address->getZipcode()),
                trim($address->getCity()),
            )));
    }

    /**
     * Get transactions array which includes required API information
     *
     * @param $payment
     * @param $issuer
     * @return array
     */

    public function getTransactions($payment,$issuer){

        $ginger_payment = self::SHOPWARE_TO_EMS_PAYMENTS[explode('emspay_',$payment->getDescription())[1]];

        return array_filter([
            array_filter([
                'payment_method' => $ginger_payment,
                'payment_method_details' => array_filter(['issuer_id' => $ginger_payment == 'ideal' ? $issuer : null])
            ])
        ]);
    }

    /**
     * Forming a Webhook url for Ginger Order based on Shopware host and Webhook controller directory
     *
     * @return string
     */

    public function getWebhookUrl(){
        return implode('',[$_SERVER['HTTP_HOST'],'/EmsPay/Webhook']);
    }

    /**
     * Function creating the extra array as additional array for a Ginger order
     * includes a Shopware order id and Plugin version
     *
     * @param $id
     * @return array
     */

    public function getExtraArray($id){
        return ['plugin' => sprintf('ShopWare 6 v%s', self::PLUGIN_VERSION),
                'sw_order_id' => $id];
    }

    /**
     * A function that calculates the total tax for positions in the order lines array
     *
     * @param $taxElements
     * @return int
     */

    protected function calculateTax($taxElements){
        $summ = 0;
        foreach ($taxElements as $tax) {
            $summ+=$tax->getTax();
        }
        return $summ;
    }

    /**
     * Function what collect array for product order line
     *
     * @param $product
     * @return array
     */

    protected function getProductLines($product){
        return [
            'name' => $product->getLabel(),
            'amount' => self::getAmountInCents($product->getTotalPrice()),
            'quantity' => (int)$product->getQuantity(),
            'vat_percentage' => (int) self::getAmountInCents(self::calculateTax($product->getPrice()->getCalculatedTaxes()->getElements())),
            'merchant_order_line_id' => (string) $product->getProductId(),
            'type' => 'physical',
            'currency' => self::DEFAULT_CURRENCY,
        ];
    }

    /**
     * Function what collect array for shipping order line
     *
     * @param $sales
     * @param $order
     * @return array
     */

    protected function getShippingLines($sales,$order){
        return [
            'name' => (string)$sales->getShippingMethod()->getName(),
            'amount' => self::getAmountInCents($order->getShippingCosts()->getTotalPrice()),
            'quantity' => $order->getShippingCosts()->getQuantity(),
            'vat_percentage' => (int) self::getAmountInCents(self::calculateTax($order->getShippingCosts()->getCalculatedTaxes()->getElements())),
            'merchant_order_line_id' => (string)$sales->getShippingMethod()->getId(),
            'type' => 'shipping_fee',
            'currency' => 'EUR',
        ];
    }

    /**
     * Function that returns order lines in an array for KP Later and Afterpay
     *
     * @param $sales
     * @param $order
     * @return array|null
     */

    public function getOrderLines($sales,$order){
        if (!in_array($sales->getPaymentMethod(),['emspay_klarnapaylater','emspay_afterpay']))
        {
            return null;
        }
        $order_lines = [];
        foreach ($order->getLineItems()->getElements() as $product){
            array_push($order_lines,self::getProductLines($product));
        }
        $order->getShippingCosts()->getUnitPrice() > 0 ? array_push($order_lines,self::getShippingLines($sales,$order)) : null;
        return $order_lines;
        }
}
