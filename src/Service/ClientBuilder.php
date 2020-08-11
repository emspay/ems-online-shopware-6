<?php

namespace Ginger\EmsPay\Service;

use Ginger\EmsPay\Exception\EmsPluginException;
use Ginger\Ginger;
use Ginger\ApiClient;
use Shopware\Core\System\SystemConfig\SystemConfigService;


class ClientBuilder{

    /**
     *  Default Ginger endpoint
     */

    const GINGER_ENDPOINT = 'https://api.online.emspay.eu';

    /**
     * Plugin settings using for create order from Ginger API
     */

    const GINGER_PLUGIN_SETTINGS = [
        'emsOnlineAfterPayCountries',
        'emsOnlineAfterpayTestIP',
        'emsOnlineApikey',
        'emsOnlineBundleCacert',
        'emsOnlineKlarnaPayLaterTestIP',
        'emsOnlineKlarnaTestApikey',
        'emsOnlineUseWebhook',
        'emsOnlineAfterpayTestApikey'
    ];

    private $config;

    public function __construct(SystemConfigService $config){
        require_once(__DIR__.'/../../vendor/autoload.php');
        $this->config = $this->setConfig($config);
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
        try{
        return Ginger::createClient(
            self::GINGER_ENDPOINT,
            $apiKey,
            $useBundle ?
                [
                    CURLOPT_CAINFO => self::getCaCertPath()
                ] : []
        );
        } catch (\Exception $exception) {
            throw new EmsPluginException($exception->getMessage());
        }
    }

    /**
     *  function get Cacert.pem path
     */

    protected static function getCaCertPath(){
        return dirname(__DIR__).'/assets/cacert.pem';
    }

    /**
     *  Get the Ginger Client using client configuration
     *
     * @param null $method
     * @return ApiClient
     */
    public function getClient($method = null)
    {
        switch ($method) {
            case 'emspay_klarnapaylater' :
                $api_key = !empty($this->config['emsOnlineKlarnaTestApikey']) ? $this->config['emsOnlineKlarnaTestApikey'] : $this->config['emsOnlineApikey'];
                break;
            case 'emspay_afterpay' :
                $api_key = !empty($this->config['emsOnlineAfterpayTestApikey']) ? $this->config['emsOnlineAfterpayTestApikey'] : $this->config['emsOnlineApikey'];
                break;
            default :
                $api_key = $this->config['emsOnlineApikey'];
        }
        return $this->getGignerClinet($api_key,$this->config['emsOnlineBundleCacert']);
    }

    /**
     * Get the system configuration of the plugin.
     *
     * @return array
     */
    public function getConfig(){
        return $this->config;
    }

    /**
     * Get the list of settings from the plugin settings page, and fill which is had the version conflict
     *
     * @param $sys
     * @return array
     */

    protected function setConfig($sys){
        $config = array_fill_keys(self::GINGER_PLUGIN_SETTINGS,null);
        $system_config = $sys->get('EmsPay.config');
        foreach (self::GINGER_PLUGIN_SETTINGS as $key){
            $config[$key] = isset($system_config[$key]) ? $system_config[$key] : $this->getDefaultValue($key);

        }
        return $config;
    }

    protected function getDefaultValue($key){
        $value = null;
        if ($key == 'emsOnlineAfterPayCountries') $value = 'NL, BE';
        return $value;
    }
}
