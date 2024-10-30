<?php

namespace Boekuwzending\WooCommerce\Helper;

use Boekuwzending\Client;
use Boekuwzending\Exception\AuthorizationFailedException;
use Boekuwzending\Exception\IncompatiblePlatformException;
use Boekuwzending\Exception\RequestFailedException;
use Boekuwzending\Utils\CompatibilityChecker;
use Boekuwzending\WooCommerce\Plugin;
use RuntimeException;
use WooCommerce;

/**
 * Class Status
 */
class Status
{
    public const MIN_WOOCOMMERCE_VERSION = '4.0.0';

    /**
     * @var string[]
     */
    protected $errors = [];

    /**
     * @var CompatibilityChecker
     */
    protected $compatibilityChecker;

    /**
     * Status constructor.
     *
     * @param CompatibilityChecker $compatibilityChecker
     */
    public function __construct(CompatibilityChecker $compatibilityChecker)
    {
        $this->compatibilityChecker = $compatibilityChecker;
    }

    /**
     * @return string[]
     */
    public function getErrors (): array
    {
        return $this->errors;
    }

    /**
     * Check if this plugin is compatible
     *
     * @return bool
     */
    public function isCompatible(): bool
    {
        static $isCompatible = null;

        if ($isCompatible !== null) {
            return $isCompatible;
        }

        // Default
        $isCompatible = true;

        if (!$this->hasCompatibleWooCommerceVersion()) {
            $this->errors[] = sprintf(
                __(
                    'The %s plugin requires at least WooCommerce version %s, you are using version %s. Please update your WooCommerce plugin.',
                    'boekuwzending-for-woocommerce'
                ),
                Plugin::PLUGIN_TITLE,
                self::MIN_WOOCOMMERCE_VERSION,
                $this->getWooCommerceVersion()
            );

            return $isCompatible = false;
        }

        if (!$this->isApiClientInstalled()) {
            $this->errors[] = __(
                'Boekuwzending API client not installed. Please make sure the plugin is installed correctly.',
                'boekuwzending-for-woocommerce'
            );

            return $isCompatible = false;
        }

        if (function_exists('extension_loaded') && !extension_loaded('json')) {
            $this->errors[] = sprintf(
                __(
                    '%s requires the JSON extension for PHP. Enable it on your server or ask your webhoster to enable it for you.',
                    'boekuwzending-for-woocommerce'
                )
            );

            return $isCompatible = false;
        }

        if (function_exists('extension_loaded') && !extension_loaded('curl')) {
            $this->errors[] = sprintf(
                __(
                    '%s requires the Curl extension for PHP. Enable it on your server or ask your webhoster to enable it for you.',
                    'boekuwzending-for-woocommerce'
                )
            );

            return $isCompatible = false;
        }

        try {
            $this->compatibilityChecker->check();
        } catch (IncompatiblePlatformException $exception) {
            switch ($exception->getCode()) {
                case IncompatiblePlatformException::INCOMPATIBLE_PHP_VERSION:
                    $error = sprintf(
                        __(
                            '%s requires PHP %s or higher, you have PHP %s. Please upgrade.',
                            'boekuwzending-for-woocommerce'
                        ),
                        Plugin::PLUGIN_TITLE,
                        CompatibilityChecker::MIN_PHP_VERSION,
                        PHP_VERSION
                    );
                    break;

                case IncompatiblePlatformException::INCOMPATIBLE_JSON_EXTENSION:
                    $error = sprintf(__(
                        "%s requires the PHP extension JSON to be enabled. Please enable the 'json' extension in your PHP configuration.",
                        'boekuwzending-for-woocommerce'
                    ), Plugin::PLUGIN_TITLE);
                    break;

                case IncompatiblePlatformException::INCOMPATIBLE_CURL_EXTENSION:
                    $error = sprintf(__(
                        "%s requires the PHP extension cURL to be enabled. Please enable the 'curl' extension in your PHP configuration.",
                        'boekuwzending-for-woocommerce'
                    ), Plugin::PLUGIN_TITLE);
                    break;

                default:
                    $error = $exception->getMessage();
                    break;
            }

            $this->errors[] = $error;

            return $isCompatible = false;
        }

        return $isCompatible;
    }

    /**
     * @return string
     */
    public function getWooCommerceVersion(): string
    {
        return WooCommerce::instance()->version;
    }

    /**
     * @return bool
     */
    public function hasCompatibleWooCommerceVersion(): bool
    {
        return (bool)version_compare($this->getWooCommerceVersion(), self::MIN_WOOCOMMERCE_VERSION, ">=");
    }

    /**
     * @return bool
     */
    protected function isApiClientInstalled(): bool
    {
        return class_exists(Client::class);
    }

    /**
     * @throws RuntimeException
     */
    public function getBoekuwzendingApiStatus(): void
    {
        try {
            // Is test mode enabled?
            $apiClient = Plugin::getApiHelper()->getApiClient();

            // Try to load me endpoint
            $apiClient->me->get();
        } catch (AuthorizationFailedException | RequestFailedException $e) {
            throw new RuntimeException(
                __('Incorrect API credentials or other authentication issue. Please check your API credentials!')
            );
        }
    }
}