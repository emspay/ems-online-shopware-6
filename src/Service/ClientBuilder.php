<?php

namespace GingerPlugin\Service;

use Ginger\Ginger;
use GingerPlugin\Components\BankConfig;
use GingerPlugin\Components\GingerExceptionHandlerTrait;
use GingerPlugin\Exception\CustomPluginException;


class ClientBuilder
{
    use GingerExceptionHandlerTrait;

    public $config;
    private $api_key;

    /**
     * Create a Ginger client instance.
     *
     * @param string $apiKey
     * @param boolean $useBundle
     * @return \Ginger\ApiClient|void
     */
    public function getGingerClient(string $apiKey, bool $useBundle)
    {
        $this->api_key = $apiKey;
        try {
            return Ginger::createClient(
                BankConfig::API_ENDPOINT,
                $apiKey,
                $useBundle ?
                    [
                        CURLOPT_CAINFO => self::getCaCertPath()
                    ] : [],
            );
        } catch (\Exception $exception) {
            $this->handleException($exception);
        }
    }

    /**
     *  Function get caCert.pem path
     */
    protected static function getCaCertPath()
    {
        return dirname(__DIR__) . '/assets/cacert.pem';
    }

    /**
     *  Get the Ginger Client using client configuration
     */
    public function getClient($method = null)
    {
        switch ($method) {
            case 'klarnapaylater' :
                $api_key = $this->config['GingerKlarnaTestAPIKey'] ?? $this->config['GingerAPIKey'];
                break;
            case 'afterpay' :
                $api_key = $this->config['GingerAfterpayTestAPIKey'] ?? $this->config['GingerAPIKey'];
                break;
            default :
                $api_key = $this->config['GingerAPIKey'] ?? '';
        };
        return $api_key ? $this->getGingerClient($api_key, $this->config['GingerBundleCacert']) : false;
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
     * @param $configService
     * @return array
     */
    protected function setConfig($configService)
    {
        $typical_configuration = array_fill_keys(BankConfig::PLUGIN_SETTINGS, null);
        $config = $configService->get(implode('.', [BankConfig::PLUGIN_TECH_PREFIX, 'config']));
        foreach (BankConfig::PLUGIN_SETTINGS as $key) {
            $typical_configuration[$key] = $config[$key] ?? $this->getDefaultValue($key);
        }
        return $typical_configuration;
    }

    protected function getDefaultValue($key): ?string
    {
        $value = null;
        if ($key == 'GingerAfterPayCountries') $value = 'NL, BE';
        return $value;
    }
}
