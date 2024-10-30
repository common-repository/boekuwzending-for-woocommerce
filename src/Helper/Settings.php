<?php

namespace Boekuwzending\WooCommerce\Helper;

use Boekuwzending\Client;
use Boekuwzending\WooCommerce\Plugin;
use Exception;
use WC_Order;

/**
 * Class Settings
 */
class Settings
{
    public const PREFIX = 'boekuwzending_wc';

    /**
     * @return bool
     */
    public function isTestModeEnabled(): bool
    {
        return 'yes' === trim(get_option($this->getSettingId('test_mode_enabled')));
    }

    public function isOrderStatusChangeThroughWebhooksEnabled(): bool
    {
        return 'yes' === trim(get_option($this->getSettingId('label_webhook_change_order_status'), 'yes'));
    }

    /**
     * Returns the configured status that orders must be moved to when a label-webhook is received. But only when enabled, see {@see isOrderStatusChangeThroughWebhooksEnabled}.
     */
    public function getShippedOrderStatus(): string
    {
        return str_replace('wc-', null, trim(get_option($this->getSettingId('label_webhook_change_order_status_status'), 'completed')));
    }

    /**
     * On which status to sync the order to the platform.
     */
    public function getSendOrderToPlatformStatus(): string
    {
        return str_replace('wc-', null, trim(get_option($this->getSettingId('sync_orders_status'), 'processing')));
    }

    /**
     * @return string|null
     */
    public function getDefaultWeight(): ?string
    {
        $weight = trim(get_option($this->getSettingId('default_weight')));

        if (empty($weight)) {
            return 1;
        }

        return $weight;
    }

    /**
     * @return string|null
     */
    public function getDefaultLength(): ?string
    {
        $length = trim(get_option($this->getSettingId('default_length')));

        if (empty($length)) {
            return 10;
        }

        return $length;
    }

    /**
     * @return string|null
     */
    public function getDefaultWidth(): ?string
    {
        $width = trim(get_option($this->getSettingId('default_width')));

        if (empty($width)) {
            return 10;
        }

        return $width;
    }

    /**
     * @return string|null
     */
    public function getDefaultHeight(): ?string
    {
        $height = trim(get_option($this->getSettingId('default_height')));

        if (empty($height)) {
            return 10;
        }

        return $height;
    }

    /**
     * @param WC_Order|null $order
     * @return bool
     */
    public function isUsingMatrices(?WC_Order $order = null): bool
    {
        if (null !== $order) {
            $shipments = $order->get_meta('_boekuwzending_shipments');

            if (!empty($shipments) && null !== boekuwzendingGetShippingMethodForOrder($order)) {
                return true;
            }
        }

        return $this->isShipmentMatricesEnabled();
    }
    /**
     * @param WC_Order|null $order
     * @return bool
     */
    public function isUsingSyncOrders(?WC_Order $order = null): bool
    {
        if (null !== $order) {
            $orders = $order->get_meta('_boekuwzending_orders');

            if (!empty($orders)) {
                return true;
            }
        }

        return $this->isSyncingOrdersEnabled();
    }

    /**
     * @param WC_Order|null $order
     * @return bool
     */
    public function createShipmentOnPaymentEnabled(): bool
    {
        if($this->isSyncingOrdersEnabled()) {
            return false;
        }

        return trim(get_option($this->getSettingId('shipments_on_payment_enabled'))) === 'yes';
    }

    /**
     * @param WC_Order|null $order
     * @return bool
     */
    public function isSyncingOrdersEnabled(): bool
    {
        return trim(get_option($this->getSettingId('sync_orders'))) === 'yes';
    }

    /**
     * @param WC_Order|null $order
     * @return bool
     */
    public function isShipmentMatricesEnabled(): bool
    {
        return trim(get_option($this->getSettingId('matrices_enabled'))) === 'yes';
    }

    /**
     * @return bool
     */
    public function isDebugEnabled(): bool
    {
        return get_option($this->getSettingId('debug'), 'yes') === 'yes';
    }

    public function isAdminErrorMailEnabled(): bool
    {
        return get_option($this->getSettingId('admin_error_mail'), 'yes') === 'yes';
    }

    /**
     * @return string
     */
    public function getGlobalSettingsUrl(): string
    {
        return admin_url(sprintf('admin.php?page=wc-settings&tab=%s', self::PREFIX));
    }

    /**
     * @return string
     */
    public function getLogsUrl(): string
    {
        return admin_url('admin.php?page=wc-status&tab=logs');
    }

    /**
     * @return string
     */
    public function getDocsUrl(): string
    {
        return 'https://docs.boekuwzending.com/plugins/woocommerce';
    }

    /**
     * @param bool $testMode
     * @return array
     */
    public function getClientCredentials(): array
    {
        $setting_client_id = 'api_client_id';
        $setting_client_secret = 'api_client_secret';

        $apiClientIdKey = $this->getSettingId($setting_client_id);
        $apiClientId = get_option($apiClientIdKey);

        $apiClientSecretKey = $this->getSettingId($setting_client_secret);
        $apiClientSecret = get_option($apiClientSecretKey);

        if (!$apiClientId && is_admin()) {
            $apiClientId = filter_input(INPUT_POST, $apiClientIdKey, FILTER_SANITIZE_STRING);
        }

        if (!$apiClientSecret && is_admin()) {
            $apiClientSecret = filter_input(INPUT_POST, $apiClientSecretKey, FILTER_SANITIZE_STRING);
        }

        return [
            'environment' => Client::ENVIRONMENT_LIVE,
            'clientId' => $apiClientId,
            'clientSecret' => $apiClientSecret,
        ];
    }

    /**
     * Get plugin status
     *
     * - Check compatibility
     * - Check Boekuwzending API connectivity
     *
     * @return string
     */
    public function getPluginStatus(): string
    {
        $status = Plugin::getStatusHelper();

        if (!$status->isCompatible()) {
            // Just stop here!
            return ''
                . '<div class="notice notice-error">'
                . '<p><strong>' . __(
                    'Error',
                    'boekuwzending-for-woocommerce'
                ) . ':</strong> ' . implode('<br/>', $status->getErrors())
                . '</p></div>';
        }

        try {
            // Check compatibility
            $status->getBoekuwzendingApiStatus();

            $api_status = ''
                . '<p>' . __('Boekuwzending API status:', 'boekuwzending-for-woocommerce')
                . ' <span style="color:green; font-weight:bold;">' . __(
                    'Connected',
                    'boekuwzending-for-woocommerce'
                ) . '</span>'
                . '</p>';

            $api_status_type = 'notice-success';
        } catch (Exception $e) {
            $api_status = ''
                . '<p style="font-weight:bold;"><span style="color:red;">Communicating with Boekuwzending API failed:</span> ' . $e->getMessage(
                ) . '</p>';

            $api_status_type = 'notice-error';
        }

        return ''
            . '<div id="message" class="' . $api_status_type . ' fade notice">'
            . $api_status
            . '</div>';
    }

    /**
     * @param string $setting
     * @return string
     */
    public static function getSettingId($setting): string
    {
        $setting_id = self::PREFIX . '_' . trim($setting);
        $setting_id_length = strlen($setting_id);

        $max_option_name_length = 191;

        if ($setting_id_length > $max_option_name_length) {
            trigger_error(
                "Setting id $setting_id ($setting_id_length) to long for database column wp_options.option_name which is varchar($max_option_name_length).",
                E_USER_WARNING
            );
        }

        return $setting_id;
    }
}