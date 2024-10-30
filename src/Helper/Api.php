<?php

namespace Boekuwzending\WooCommerce\Helper;

use Boekuwzending\Client;
use Boekuwzending\ClientFactory;
use Boekuwzending\Resource\Matrix;
use Boekuwzending\Resource\Shipment;
use Boekuwzending\WooCommerce\Plugin;
use Exception;
use RuntimeException;

/**
 * Class Api
 */
class Api
{
    /**
     * @var Client
     */
    protected static $apiClient;

    /**
     * @var Settings
     */
    protected $settingsHelper;

    /**
     * @param Settings $settingsHelper
     */
    public function __construct(Settings $settingsHelper)
    {
        $this->settingsHelper = $settingsHelper;
    }

    /**
     * @param bool $testMode
     * @return Client
     * @throws RuntimeException
     */
    public function getApiClient(): Client
    {
        global $wp_version;

        $apiCredentials = $this->settingsHelper->getClientCredentials();

        if (has_filter('boekuwzending_api_credentials_filter')) {
            $apiCredentials = apply_filters('boekuwzending_api_credentials_filter', $apiCredentials);
        }

        if (empty($apiCredentials['clientId'])) {
            throw new RuntimeException(
                __(
                    'No Client Id provided. Please set your Boekuwzending API Client Id',
                    'boekuwzending-for-woocommerce'
                )
            );
        }

        if (empty($apiCredentials['clientSecret'])) {
            throw new RuntimeException(
                __(
                    'No Client Secret provided. Please set your Boekuwzending API Client Secret',
                    'boekuwzending-for-woocommerce'
                )
            );
        }

        if (empty(self::$apiClient)) {
            $client = ClientFactory::build(
                $apiCredentials['clientId'],
                $apiCredentials['clientSecret'],
                $apiCredentials['environment']
            );

            $client->addAdditionalUserAgent('WordPress/' . $wp_version ?? 'Unknown');
            $client->addAdditionalUserAgent('WooCommerce/' . get_option('woocommerce_version', 'Unknown'));
            $client->addAdditionalUserAgent('BoekuwzendingWoo/' . Plugin::PLUGIN_VERSION);

            self::$apiClient = $client;
        }

        return self::$apiClient;
    }

    /**
     * @param bool $testMode
     * @return bool
     */
    public function isIntegrated(): bool
    {
        $apiCredentials = $this->settingsHelper->getClientCredentials();

        return !(empty($apiCredentials['clientId']) || empty($apiCredentials['clientSecret']));
    }

    public function getMatrix(Shipment $shipment): ?Matrix
    {
        try {
            return $this->getApiClient()->shipment->getMatrix($shipment);
        } catch (Exception $throwable) {
            Plugin::logException(__METHOD__, $throwable);

            return null;
        }
    }
}
