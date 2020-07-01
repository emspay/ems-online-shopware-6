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
     * The function which call the method what return a link to finalize action
     * @param $transaction
     * @return string
     */
    public function getReturnUrl($transaction){
        return $transaction->getReturnUrl();
    }

    public function getAmountInCents(){
        return '';
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

    public function getCustomer(){
        return '';
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
