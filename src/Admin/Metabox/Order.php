<?php
/** @noinspection PhpIncludeInspection */

namespace Boekuwzending\WooCommerce\Admin\Metabox;

use Boekuwzending\WooCommerce\Helper\Api;
use Boekuwzending\WooCommerce\Helper\Data;
use Boekuwzending\WooCommerce\Helper\Settings;
use Boekuwzending\WooCommerce\Plugin;

/**
 * Class Order
 */
class Order
{
    /**
     * @var Data
     */
    protected static $dataHelper;

    /**
     * @var Settings
     */
    protected static $settingsHelper;

    /**
     * @var Api
     */
    protected static $apiHelper;

    /**
     * Initialize
     *
     * @param Settings $settingsHelper
     * @param Api $apiHelper
     * @param Data $dataHelper
     */
    public static function init(Settings $settingsHelper, Api $apiHelper, Data $dataHelper): void
    {
        self::$dataHelper = $dataHelper;
        self::$settingsHelper = $settingsHelper;
        self::$apiHelper = $apiHelper;

        add_action( 'add_meta_boxes', [__CLASS__, 'add'] );
        add_action('wp_ajax_boekuwzending_admin_metabox_order_render', [__CLASS__, 'render']);

    }

    /**
     * Add custom metabox to WooCommerce order page
     */
    public static function add(): void
    {
        $screen = class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' )
            ? wc_get_page_screen_id( 'shop-order' )
            : 'shop_order';

        add_meta_box(
            'woocommerce-order-boekuwzending',
            __( 'Boekuwzending', 'boekuwzending-for-woocommerce' ),
            [__CLASS__, 'renderBase'],
            $screen,
            'side',
            'default'
        );
    }

    /**
     * Renders the general container for the metabox
     *
     * @noinspection PhpIncludeInspection
     */
    public static function renderBase(): void
    {
        // Render metabox view
        require_once Plugin::getTemplatePath('admin/order/edit/meta/render');

        // Render script template for add shipping method backbone modal
        require_once Plugin::getTemplatePath('admin/order/edit/modal/add-shipping-method');

        // Render script template for change shipping method backbone modal
        require_once Plugin::getTemplatePath('admin/order/edit/modal/change-shipping-method');

        require_once Plugin::getTemplatePath('admin/order/edit/modal/get-shipping-services');
    }

    /**
     * Renders the possible actions based on the specific requirements
     */
    public static function render(): void
    {
        $orderId = absint(wp_unslash($_POST['orderId']));

        if (empty($orderId)) {
            return;
        }

        $order = wc_get_order($orderId);

        if (!$order) {
            return;
        }

        if (false === self::$apiHelper->isIntegrated()) {
            require_once Plugin::getTemplatePath('admin/order/edit/meta/not_connected');

            die();
        }

        $refreshStatusUrl = wp_nonce_url(
            admin_url('admin-ajax.php?action=boekuwzending_admin_retrieve_status&screen=details&order_id=' . $order->get_id()),
            'boekuwzending-retrieve-status'
        );

        $downloadLabelsUrl = wp_nonce_url(
            admin_url('admin-ajax.php?action=boekuwzending_admin_download_labels&screen=details&order_id=' . $order->get_id()),
            'boekuwzending-download-labels'
        );

        $createAdditionalLabelUrl = wp_nonce_url(
            admin_url(
                'admin-ajax.php?action=boekuwzending_admin_create_additional_label&screen=details&order_id=' . $order->get_id()
            ),
            'boekuwzending-create-additional-label'
        );

	    $createNewOrderUrl = wp_nonce_url(
            admin_url(
                'admin-ajax.php?action=boekuwzending_admin_create_order&screen=details&order_id=' . $order->get_id()
            ),
            'boekuwzending-create-order'
        );

        $isUsingSyncOrders = self::$settingsHelper->isUsingSyncOrders($order);
        $orders = self::$dataHelper->getBoekuwzendingOrders($order->get_id());

        $isUsingMatrices = self::$settingsHelper->isUsingMatrices($order);
        $shippingMethod = boekuwzendingGetShippingMethodForOrder($order);
        $shipments = self::$dataHelper->getBoekuwzendingShipments($order->get_id());
        $status = self::$dataHelper->getBoekuwzendingStatus($order->get_id());

        /** @noinspection PhpUnusedLocalVariableInspection */
        $shipmentData = self::$dataHelper->getBoekuwzendingData($order->get_id());

        /** @noinspection PhpUnusedLocalVariableInspection */
        $showPickupPointInformation = $shippingMethod && wc_string_to_bool(
                $shippingMethod->get_meta('_pick_up')
            );
        /** @noinspection PhpUnusedLocalVariableInspection */
        $isNewOrder = wc_string_to_bool($_POST['isNewOrder']);

        require_once Plugin::getTemplatePath('admin/order/edit/meta/status');

        $orderItems = $order->get_items( ['line_item'] );

        if (count($orderItems) > 0) {
            if ($isUsingSyncOrders) {
                require_once Plugin::getTemplatePath('admin/order/edit/meta/order_actions');
            } else {
                require_once Plugin::getTemplatePath('admin/order/edit/meta/shipment_actions');
            }
        } else {
            require_once Plugin::getTemplatePath('admin/order/edit/meta/no_products');
        }

        die();
    }
}