<?php

namespace Ginger\EmsPay\Service;

use Ginger\Ginger;
use Ginger\ApiClient;
use Shopware\Core\System\SystemConfig\SystemConfigService;


class ClientBuilder{

    /**
     *  Default Ginger endpoint
     */

    const GINGER_ENDPOINT = 'https://api.online.emspay.eu';

    private $config;

    public function __construct(SystemConfigService $config)
    {
        include (dirname(__FILE__)."/../Vendor/vendor/autoload.php");
        $this->config = $config->get('EmsPay.config');
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
                $api_key = !empty(['emsOnlineAfterpayTestApikey']) ? $this->config['emsOnlineAfterpayTestApikey'] : $this->config['emsOnlineApikey'];
                break;
            default :
                $api_key = $this->config['emsOnlineApikey'];
        }
        return $this->getGignerClinet($api_key,$this->config['emsOnlineBundleCacert']);
    }

    /**
     * Get the system configuration of the plugin.
     *
     * @return SystemConfigService
     */
    public function getConfig(){
        return $this->config;
    }
}
