<?php

namespace GingerPlugin\emspay\Service;

use Ginger\ApiClient;
use GingerPlugin\emspay\Exception\EmsPluginException;
use Ginger\Ginger;
use Shopware\Core\System\SystemConfig\SystemConfigService;


class ClientBuilder
{

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
        'emsOnlineAfterpayTestApikey'
    ];

    private $config;

    public function __construct(SystemConfigService $config)
    {
        $this->config = $this->setConfig($config);
    }

    /**
     * Create a Gigner client instance.
     *
     * @param string $apiKey
     * @param boolean $useBundle
     * @return ApiClient
     */
    public function getGignerClinet(string $apiKey, $useBundle = false): ApiClient
    {
        try {
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
     *  function get caCert.pem path
     */

    protected static function getCaCertPath()
    {
        return dirname(__DIR__) . '/assets/cacert.pem';
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
        return $this->getGignerClinet($api_key, $this->config['emsOnlineBundleCacert']);
    }

    /**
     * Get the system configuration of the plugin.
     *
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Get the list of settings from the plugin settings page, and fill which is had the version conflict
     *
     * @param $sys
     * @return array
     */

    protected function setConfig($sys)
    {
        $config = array_fill_keys(self::GINGER_PLUGIN_SETTINGS, null);
        $system_config = $sys->get('emspay.config');
        foreach (self::GINGER_PLUGIN_SETTINGS as $key) {
            $config[$key] = isset($system_config[$key]) ? $system_config[$key] : $this->getDefaultValue($key);

        }
        return $config;
    }

    protected function getDefaultValue($key)
    {
        $value = null;
        if ($key == 'emsOnlineAfterPayCountries') $value = 'NL, BE';
        return $value;
    }
}
