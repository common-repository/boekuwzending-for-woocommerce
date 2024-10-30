<?php
/** @noinspection PhpIncludeInspection */

namespace Boekuwzending\WooCommerce\Admin;

use Boekuwzending\Exception\AuthorizationFailedException;
use Boekuwzending\Exception\RequestFailedException;
use Boekuwzending\Resource\Address;
use Boekuwzending\Resource\Contact;
use Boekuwzending\Resource\DispatchInstruction;
use Boekuwzending\Resource\Item;
use Boekuwzending\Resource\Shipment;
use Boekuwzending\WooCommerce\Helper\Api;
use Boekuwzending\WooCommerce\Helper\Data;
use Boekuwzending\WooCommerce\Helper\Notice;
use Boekuwzending\WooCommerce\Helper\Settings;
use Boekuwzending\WooCommerce\Method\Delivery;
use Boekuwzending\WooCommerce\Method\PickUpPoint;
use Boekuwzending\WooCommerce\Plugin;
use Boekuwzending\WooCommerce\Settings\Page as SettingsPage;
use DateTime;
use Exception;
use WC_Order;
use WC_Order_Item;
use WC_Order_Item_Shipping;
use WC_Shipping_Rate;

class Hooks
{
    /**
     * @var Settings
     */
    protected static $settingsHelper;

    /**
     * @var Data
     */
    protected static $dataHelper;

    /**
     * @var Api
     */
    protected static $apiHelper;

    /**
     * @var Notice
     */
    protected static $noticeHelper;

    /**
     * Initialize plugin
     *
     * @param Settings $settingsHelper
     * @param Data $dataHelper
     * @param Api $apiHelper
     * @param Notice $noticeHelper
     */
    public static function init(Settings $settingsHelper, Data $dataHelper, Api $apiHelper, Notice $noticeHelper): void
    {
        $pluginBasename = Plugin::getPluginFile();

        self::$settingsHelper = $settingsHelper;
        self::$dataHelper = $dataHelper;
        self::$apiHelper = $apiHelper;
        self::$noticeHelper = $noticeHelper;

        // Add settings link to plugins page
        add_filter('plugin_action_links_' . $pluginBasename, [__CLASS__, 'addPluginActionLinks'], 10, 5);

        // Modify Order overview screen
        add_filter('manage_edit-shop_order_columns', [__CLASS__, 'addBoekuwzendingStatusColumn'], 20, 1);
        //hpos equivelant of above filter
        add_filter('manage_woocommerce_page_wc-orders_columns', [__CLASS__, 'addBoekuwzendingStatusColumn'], 20, 1);

        add_action('manage_shop_order_posts_custom_column', [__CLASS__, 'modifyDestinationColumn'], 20, 2);
        //hpos eqaivelant
        add_action('manage_woocommerce_page_wc-orders_custom_column', [__CLASS__, 'modifyDestinationColumn'], 20, 2);


        add_filter('woocommerce_admin_order_actions', [__CLASS__, 'addBoekuwzendingOrderActions'], 10, 2);
        add_action('admin_head', [__CLASS__, 'addBoekuwzendingOrderActionsCss']);

        // Modify Order Edit screen
        add_filter('woocommerce_hidden_order_itemmeta', [__CLASS__, 'hideShippingMethodMetaData'], 10, 1);
        add_action('woocommerce_before_order_itemmeta', [__CLASS__, 'addShippingMethodInformation'], 10, 2);
        add_action('woocommerce_after_order_itemmeta', [__CLASS__, 'addShippingMethodWidget'], 10, 2);

        // Enqueue Scripts
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueueAdminScripts']);

        // Ajax actions
        add_action('wp_ajax_boekuwzending_admin_create_order', [__CLASS__, 'createOrder']);
        add_action('wp_ajax_boekuwzending_admin_create_shipment', [__CLASS__, 'createShipment']);
        add_action('wp_ajax_boekuwzending_admin_create_additional_label', [__CLASS__, 'createAdditionalLabel']);
        add_action('wp_ajax_boekuwzending_admin_retrieve_status', [__CLASS__, 'retrieveStatus']);
        add_action('wp_ajax_boekuwzending_admin_retrieve_labels', [__CLASS__, 'retrieveSingleLabel']);
        add_action('wp_ajax_boekuwzending_admin_download_labels', [__CLASS__, 'downloadLabels']);
        add_action('wp_ajax_boekuwzending_admin_add_shipping_method', [__CLASS__, 'addShippingMethod']);
        add_action('wp_ajax_boekuwzending_admin_save_delivery', [__CLASS__, 'saveDeliveryInformation']);
        add_action('wp_ajax_boekuwzending_admin_save_pick_up_point', [__CLASS__, 'savePickUpPointInformation']);
        add_action('wp_ajax_boekuwzending_admin_get_services', [__CLASS__, 'getServices']);

        // We add our display_flash_notices function to the admin_notices
        add_action('admin_notices', [Notice::class, 'render'], 12);

        add_action('admin_footer', [__CLASS__, 'renderCreateShipmentModal']);

        add_filter(
            'woocommerce_get_settings_pages',
            static function ($settings) {
                $settings[] = new SettingsPage(self::$settingsHelper);

                return $settings;
            }
        );

        self::registerAdminScripts();
    }

    /**
     * Register Scripts
     *
     * @return void
     */
    public static function registerAdminScripts(): void
    {
        wp_register_style(
            'buz_wc_admin_order',
            Plugin::getPluginUrl('/public/admin/order.min.css'),
            ['buz_wc_pickup_points_loader'],
            filemtime(Plugin::getPluginPath('/public/admin/order.min.css')),
            'screen'
        );

        wp_register_script(
            'buz_wc_admin_order',
            Plugin::getPluginUrl('/public/admin/order.min.js'),
            ['buz_wc_pickup_points_loader'],
            filemtime(Plugin::getPluginPath('/public/admin/order.min.js')),
            true
        );
    }

    /**
     * @throws AuthorizationFailedException
     * @noinspection PhpUnused
     */
    public static function enqueueAdminScripts(): void
    {
        global $post;

        $orderId = $post->id;

        if (!$orderId && isset($_GET['id']) || isset($_GET['post'])) {
            $orderId = $_GET['id'] ?? $_GET['post'];
        }

        $current_screen = get_current_screen();

        if (
            !is_admin()
            || (!$current_screen ||
                (
                    $current_screen->id !== 'shop_order'
                    && $current_screen->id !== 'edit-shop_order'
                    && $current_screen->post_type !== 'shop_order'
                    && $current_screen->post_type !== 'edit-shop_order'
                )
            )
        ) {
            return;
        }

        $token = null;

        if (self::$apiHelper->isIntegrated()) {
            try {
                $token = self::$apiHelper->getApiClient()->authorize(
                    ['pickup_point']
                );
            } catch (Exception $e) {
                // TODO: admin notice
                $token = null;
            }
        }

        $url = 'https://mijn.boekuwzending.com/pickup-points';
        $isShopOrderScreen = $current_screen->id === 'shop_order' || $current_screen->post_type === 'shop_order';

        $orderId = $isShopOrderScreen && ($current_screen->action === 'add' || (!empty($_GET['action']) && $_GET['action'] === 'edit')) ? $orderId : null;
        $isNewOrder = $isShopOrderScreen && $current_screen->action === 'add';

        wp_localize_script(
            'buz_wc_admin_order',
            'buz_wc_settings',
            [
                'url' => $url,
                'token' => $token,
                'ajax_url' => admin_url('admin-ajax.php'),
                'ajax_nonce' => wp_create_nonce('buz_wc'),
                'order_id' => $orderId,
                'is_new_order' => $isNewOrder,
                'i18n_error_while_saving_rate' => esc_html__('An error occurred while saving the shipping rate', 'boekuwzending-for-woocommerce'),
                'i18n_error_while_saving_pick_up' => esc_html__('An error occurred while saving the pick up point', 'boekuwzending-for-woocommerce'),
                'i18n_no_shipping_rate_found' => esc_html__('No available shipping rate was found', 'boekuwzending-for-woocommerce'),
                'i18n_an_error_occured' => esc_html__('An error occurred', 'boekuwzending-for-woocommerce'),
                'i18n_no_shipping_information' => esc_html__('No shipping information was given', 'boekuwzending-for-woocommerce'),
                'i18n_no_shipping_postcode' => esc_html__('No shipping postcode was given', 'boekuwzending-for-woocommerce'),
                'i18n_no_shipping_address' => esc_html__('No shipping address was given', 'boekuwzending-for-woocommerce'),
                'i18n_no_shipping_city' => esc_html__('No shipping city was given', 'boekuwzending-for-woocommerce'),
                'i18n_no_shipping_country' => esc_html__('No shipping country was given', 'boekuwzending-for-woocommerce'),
                'i18n_unknown_error_occured' => esc_html__('An unknown error occured', 'boekuwzending-for-woocommerce'),
            ]
        );

        wp_enqueue_style('buz_wc_admin_order');
        wp_enqueue_script('buz_wc_admin_order');
    }

    /**
     * @param array $columns
     * @return array
     * @noinspection PhpUnused
     */
    public static function addBoekuwzendingStatusColumn(array $columns): array
    {
        // Order Action Column
        $actionColumn = $columns['wc_actions'];

        unset($columns['wc_actions']);

        $columns['buz_status'] = '<img src="' . Plugin::getPluginUrl(
                'public/img/icon.png'
            ) . '" alt="Boekuwzending" style="height: 24px; vertical-align: middle;" />';

        $columns['wc_actions'] = $actionColumn;

        return $columns;
    }

    /**
     * @param string $column
     * @param string $id
     * @noinspection PhpUnused
     */
    public static function modifyDestinationColumn(string $column, string $id): void
    {
        if (json_decode($id, true)) {
            $id = json_decode($id)->id;
        }

        $data = self::$dataHelper->getBoekuwzendingData($id);
        $status = self::$dataHelper->getBoekuwzendingStatus($id);
        $shipments = self::$dataHelper->getBoekuwzendingShipments($id);

        if (($column === 'shipping_address') && $data && !empty($data->pick_up_point)) {
            /** @noinspection PhpUnusedLocalVariableInspection */
            $pickUpPoint = $data->pick_up_point;

            $informationTemplate = apply_filters(
                'boekuwzending_template_order_overview_pick_up_point',
                Plugin::getTemplatePath('admin/order/list/pick_up_point_information')
            );

            if (file_exists($informationTemplate)) {
                include $informationTemplate;
            }
        }

        if ($column === 'buz_status' && !empty($shipments)) {
            foreach ($shipments as $shipment) {
                foreach ($shipment['labels'] as $label) {
                    $statusString = '';

                    if (isset($status[$label['id']]['status'])) {
                        $statusString = Plugin::translateLabelStatus($status[$label['id']]['status']);
                    }

                    $url = wp_nonce_url(
                        admin_url(
                            sprintf(
                                'admin-ajax.php?action=boekuwzending_admin_retrieve_labels&shipment_id=%s',
                                $shipment['id']
                            )
                        ),
                        'boekuwzending-retrieve-labels'
                    );

                    echo sprintf(
                        '<div><a href="%s">%s</a>&nbsp;<div class="status-tracking-text">%s</div></div>',
                        $url,
                        $label['waybill'],
                        $statusString
                    );
                }
            }
        }
    }

    /**
     * @param array $actions
     * @param WC_Order $order
     * @return array
     * @noinspection PhpUnused
     */
    public static function addBoekuwzendingOrderActions(array $actions, WC_Order $order): array
    {
        $shipments = self::$dataHelper->getBoekuwzendingShipments($order->get_id());
        $orders = self::$dataHelper->getBoekuwzendingOrders($order->get_id());
        $shippingMethod = boekuwzendingGetShippingMethodForOrder($order);

        $isUsingSyncOrders = self::$settingsHelper->isUsingSyncOrders($order);
        $isUsingMatrices = self::$settingsHelper->isUsingMatrices($order);

        if ((true === $isUsingMatrices && $shippingMethod === null) || false === self::$apiHelper->isIntegrated()) {
            return $actions;
        }

        $createShipmentUrl = wp_nonce_url(
            admin_url(
                sprintf(
                    'admin-ajax.php?action=boekuwzending_admin_create_shipment&order_id=%s',
                    $order->get_id()
                )
            ),
            'boekuwzending-create-shipment'
        );

        $createOrderUrl = wp_nonce_url(
            admin_url(
                sprintf(
                    'admin-ajax.php?action=boekuwzending_admin_create_order&order_id=%s',
                    $order->get_id()
                )
            ),
            'boekuwzending-create-order'
        );

        if ($isUsingMatrices && empty($shipments)) {
            $actions['_buz_create_shipment'] = array(
                'url' => $createShipmentUrl,
                'name' => __('Create shipment', 'boekuwzending-for-woocommerce'),
                'action' => 'view create-shipment', // keep "view" class for a clean button CSS
            );
        }

        if ($isUsingSyncOrders && empty($orders)) {
            $actions['_buz_create_order'] = array(
                'url' => $createOrderUrl,
                'name' => __('Export to Boekuwzending.com', 'boekuwzending-for-woocommerce'),
                'action' => 'view create-order', // keep "view" class for a clean button CSS
            );
        }

        if (!empty($shipments)) {
            $actions['_buz_retrieve_status'] = array(
                'url' => wp_nonce_url(
                    admin_url(
                        sprintf(
                            'admin-ajax.php?action=boekuwzending_admin_retrieve_status&order_id=%s',
                            $order->get_id()
                        )
                    ),
                    'boekuwzending-retrieve-status'
                ),
                'name' => __('Retrieve status', 'boekuwzending-for-woocommerce'),
                'action' => 'view retrieve-status', // keep "view" class for a clean button CSS
            );

            $actions['_buz_download_labels'] = array(
                'url' => wp_nonce_url(
                    admin_url(
                        sprintf(
                            'admin-ajax.php?action=boekuwzending_admin_download_labels&order_id=%s',
                            $order->get_id()
                        )
                    ),
                    'boekuwzending-download-labels'
                ),
                'name' => __('Download Labels', 'boekuwzending-for-woocommerce'),
                'action' => 'view download-labels', // keep "view" class for a clean button CSS
            );

            $actions['_create_additional_label'] = array(
                'url' => wp_nonce_url(
                    admin_url(
                        sprintf(
                            'admin-ajax.php?action=boekuwzending_admin_create_additional_label&order_id=%s',
                            $order->get_id()
                        )
                    ),
                    'boekuwzending-create-additional-label'
                ),
                'name' => __('Create additional label', 'boekuwzending-for-woocommerce'),
                'action' => 'view create-additional-label', // keep "view" class for a clean button CSS
            );
        }

        return $actions;
    }

    /**
     * Add css for custom order action buttons
     * @noinspection PhpUnused
     */
    public static function addBoekuwzendingOrderActionsCss(): void
    {
        global $pagenow;

        if ($pagenow === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'shop_order') {
            echo '<style>.wc-action-button.create-order::after { font-family: woocommerce !important; content: "\e019" !important; }</style>';
            echo '<style>.wc-action-button.create-shipment::after { font-family: woocommerce !important; content: "\e019" !important; }</style>';
            echo '<style>.wc-action-button.create-additional-label::after { font-family: woocommerce !important; content: "\e02b" !important; }</style>';
            echo '<style>.wc-action-button.retrieve-status::after { font-family: woocommerce !important; content: "\e014" !important; }</style>';
            echo '<style>.wc-action-button.download-labels::after { font-family: woocommerce !important; content: "\e001" !important; }</style>';
        }
    }

    /**
     * Create order through XHR or GET request
     * @noinspection PhpUnused
     */
    public static function createOrder(): void
    {
        if (boekuwzendingIsAdminXHR()) {
            check_ajax_referer('buz_wc', 'security');

            $orderId = absint(wp_unslash($_POST['orderId']));
            $order = wc_get_order($orderId);

            if (!$order) {
                wp_send_json_error(null, 400);
            }

            if (Plugin::createOrder($order) !== null) {
                wp_send_json_success(
                    [
                        'notesHtml' => boekuwzendingWooCommerceGetOrderNotesHtml($order->get_id())
                    ]
                );
            } else {
                boekuwzendingSendJsonError();
            }
        } else {
            if (isset($_GET['order_id']) && current_user_can('edit_shop_orders') && check_admin_referer(
                    'boekuwzending-create-order'
                ) &&
                self::getPostType(absint(wp_unslash($_GET['order_id']))) === 'shop_order') {
                $orderId = absint(wp_unslash($_GET['order_id']));
                $order = wc_get_order($orderId);

                try {
                    Plugin::createOrder($order);
                } catch (Exception $e) {
                    $order->add_order_note(
                        sprintf(esc_html__('Error creating order: %s', 'boekuwzending-for-woocommerce'), $e->getMessage()),
                        0,
                        true
                    );

                    Plugin::debug(sprintf('Error creating order from order %s: %s', $order->get_id(), $e));
                }
            }

            if (!empty($_GET['screen']) && !empty($_GET['screen']) === 'details') {
                $orderId = absint(wp_unslash($_GET['orderId']));

                wp_safe_redirect(get_edit_post_link($orderId));
            } else {
                wp_safe_redirect(wp_get_referer() ?: admin_url('edit.php?post_type=shop_order'));
            }
        }
    }

    /**
     * Create shipment through XHR or GET request
     * @noinspection PhpUnused
     */
    public static function createShipment(): void
    {
        $itemData = [];
        $service = null;

        if (isset($_POST['data'])) {
            $itemData = boekuwzendingSanitizeArray($_POST['data']);
            $service = sanitize_text_field($_POST['data']['service_id']);
        }

        if (boekuwzendingIsAdminXHR()) {
            check_ajax_referer('buz_wc', 'security');

            $orderId = absint(wp_unslash($_POST['orderId']));
            $order = wc_get_order($orderId);

            if (!$order) {
                wp_send_json_error(null, 400);
            }

            if (Plugin::createShipment($order, $itemData, $service) !== null) {
                wp_send_json_success(
                    [
                        'notesHtml' => boekuwzendingWooCommerceGetOrderNotesHtml($order->get_id())
                    ]
                );
            } else {
                boekuwzendingSendJsonError();
            }
        } else {
            if (isset($_GET['order_id']) && current_user_can('edit_shop_orders') && check_admin_referer(
                    'boekuwzending-create-shipment'
                ) &&
                self::getPostType(absint(wp_unslash($_GET['order_id']))) === 'shop_order') {
                $orderId = absint(wp_unslash($_GET['order_id']));
                $order = wc_get_order($orderId);

                Plugin::createShipment($order, $itemData, $service);
            }

            if (!empty($_GET['screen']) && !empty($_GET['screen']) === 'details') {
                $orderId = absint(wp_unslash($_GET['orderId']));

                wp_safe_redirect(get_edit_post_link($orderId));
            } else {
                wp_safe_redirect(wp_get_referer() ?: admin_url('edit.php?post_type=shop_order'));
            }
        }

        exit;
    }

    /**
     * @noinspection PhpUnused
     */
    public static function createAdditionalLabel(): void
    {
        if (boekuwzendingIsAdminXHR()) {
            check_ajax_referer('buz_wc', 'security');

            $orderId = absint(wp_unslash($_POST['orderId']));
            $order = wc_get_order($orderId);

            if (!$order) {
                wp_send_json_error(null, 400);
            }

            if (Plugin::createShipment($order) !== null) {
                wp_send_json_success(
                    [
                        'notesHtml' => boekuwzendingWooCommerceGetOrderNotesHtml($order->get_id())
                    ]
                );
            } else {
                boekuwzendingSendJsonError();
            }
        } else {
            if (isset($_GET['order_id']) && current_user_can('edit_shop_orders') && check_admin_referer(
                    'boekuwzending-create-additional-label'
                ) &&
                self::getPostType(absint(wp_unslash($_GET['order_id']))) === 'shop_order') {
                $orderId = absint(wp_unslash($_GET['order_id']));
                $order = wc_get_order($orderId);

                Plugin::createShipment($order);
            }

            if (!empty($_GET['screen']) && !empty($_GET['screen']) === 'details') {
                $orderId = absint(wp_unslash($_GET['orderId']));

                wp_safe_redirect(get_edit_post_link($orderId));
            } else {
                wp_safe_redirect(wp_get_referer() ?: admin_url('edit.php?post_type=shop_order'));
            }
        }

        exit;
    }

    /**
     * @noinspection PhpUnused
     */
    public static function retrieveStatus(): void
    {
        if (boekuwzendingIsAdminXHR()) {
            check_ajax_referer('buz_wc', 'security');

            $orderId = absint(wp_unslash($_POST['orderId']));
            $order = wc_get_order($orderId);

            if (!$order) {
                wp_send_json_error(null, 400);
            }

            Plugin::retrieveStatus($order);

            wp_send_json_success();
        } else {
            if (isset($_GET['order_id']) && current_user_can('edit_shop_orders') && check_admin_referer(
                    'boekuwzending-retrieve-status'
                ) &&
                self::getPostType(absint(wp_unslash($_GET['order_id']))) === 'shop_order') {
                $orderId = absint(wp_unslash($_GET['order_id']));
                $order = wc_get_order($orderId);

                Plugin::retrieveStatus($order);
            }

            if (!empty($_GET['screen']) && !empty($_GET['screen']) === 'details') {
                $orderId = absint(wp_unslash($_GET['orderId']));

                wp_safe_redirect(get_edit_post_link($orderId));
            } else {
                wp_safe_redirect(wp_get_referer() ?: admin_url('edit.php?post_type=shop_order'));
            }
        }

        exit;
    }

    /**
     * @noinspection PhpUnused
     * @throws AuthorizationFailedException
     * @throws RequestFailedException
     */
    public static function downloadLabels(): void
    {
        if (isset($_GET['order_id']) && current_user_can('edit_shop_orders') && check_admin_referer(
                'boekuwzending-download-labels'
            ) &&
            self::getPostType(absint(wp_unslash($_GET['order_id']))) === 'shop_order') {
            $orderId = absint(wp_unslash($_GET['order_id']));
            $order = wc_get_order($orderId);

            Plugin::downloadLabels($order);
        }

        if (!empty($_GET['screen']) && !empty($_GET['screen']) === 'details') {
            $orderId = absint(wp_unslash($_GET['order_id']));

            wp_safe_redirect(get_edit_post_link($orderId));
        } else {
            wp_safe_redirect(wp_get_referer() ?: admin_url('edit.php?post_type=shop_order'));
        }

        exit;
    }

    /**
     * @noinspection PhpUnused
     * @throws AuthorizationFailedException
     * @throws RequestFailedException
     */
    public static function retrieveSingleLabel(): void
    {
        if (isset($_GET['shipment_id']) && check_admin_referer('boekuwzending-retrieve-labels')) {
            $shipmentId = wp_unslash(sanitize_key($_GET['shipment_id']));

            Plugin::retrieveLabels($shipmentId);
        }

        if (!empty($_GET['screen']) && !empty($_GET['screen']) === 'details') {
            $orderId = absint(wp_unslash($_GET['orderId']));

            wp_safe_redirect(get_edit_post_link($orderId));
        } else {
            wp_safe_redirect(wp_get_referer() ?: admin_url('edit.php?post_type=shop_order'));
        }

        exit;
    }

    /**
     * Save posted rate data to current present Boekuwzending method
     * @noinspection PhpUnused
     */
    public static function addShippingMethod(): void
    {
        check_ajax_referer('buz_wc', 'security');

        $postedRate = $_POST['rate'];

        if (empty($postedRate)) {
            wp_send_json_error(null, 400);

            die();
        }

        $orderId = absint(wp_unslash($_POST['orderId']));

        if (empty($orderId)) {
            wp_send_json_error(null, 400);

            die();
        }

        $order = wc_get_order($orderId);

        if (!$order) {
            wp_send_json_error(null, 400);
        }

        $isPickUp = !empty($postedRate['meta_data']['_pick_up']) && wc_string_to_bool(
                $postedRate['meta_data']['_pick_up']
            ) === true;

        $shippingMethod = false === $isPickUp ? new Delivery() : new PickUpPoint();
        $orderItem = new WC_Order_Item_Shipping();

        $rateId = absint(wp_unslash($postedRate['id']));
        $rateLabel = sanitize_text_field($postedRate['label']);
        $rateCost = (float)wp_unslash($postedRate['cost']);

        $existingShipping = $order->get_shipping_methods();

        /* If there's already a non-buz shipping method (like "flat rate") and the admin chooses
         * another method from the matrix, don't add the matrix cost to the order.
         */
        foreach ($existingShipping as $shipping) {
            if (isset($shipping->get_data()['method_id']) && stripos($shipping->get_data()['method_id'], 'buz_') === false) {
                $rateCost = 0;
                break;
            }
        }

        $rate = new WC_Shipping_Rate();
        $rate->set_id($rateId);
        $rate->set_method_id($shippingMethod->id);
        $rate->set_instance_id($shippingMethod->get_instance_id());
        $rate->set_label($rateLabel);
        $rate->set_cost($rateCost);

        if (!empty($postedRate['meta_data'])) {
            foreach ($postedRate['meta_data'] as $key => $value) {
                $key = sanitize_key($key);
                $value = sanitize_text_field($value);

                $rate->add_meta_data($key, $value);
                $orderItem->add_meta_data($key, $value, true);
            }
        }

        $orderItem->set_shipping_rate($rate);
        $orderItem->save();

        $order->add_item($orderItem);
        $order->calculate_totals();

        wp_send_json_success();

        die();
    }

    /**
     * Save posted rate data to current present Boekuwzending method
     * @noinspection PhpUnused
     */
    public static function saveDeliveryInformation(): void
    {
        check_ajax_referer('buz_wc', 'security');

        $postedRate = $_POST['rate'];

        if (empty($postedRate)) {
            wp_send_json_error(null, 400);

            die();
        }

        $orderId = absint(wp_unslash($_POST['orderId']));
        $orderItemId = absint(wp_unslash($_POST['orderItemId']));

        if (empty($orderId) || empty($orderItemId)) {
            wp_send_json_error(null, 400);

            die();
        }

        $order = wc_get_order($orderId);

        if (!$order) {
            wp_send_json_error(null, 400);
        }

        foreach ($order->get_shipping_methods() as $order_item_id => $order_item) {
            if ($order_item_id === $orderItemId) {
                $rateId = absint(wp_unslash($postedRate['id']));
                $rateLabel = sanitize_text_field($postedRate['label']);
                $rateCost = (float)wp_unslash($postedRate['cost']);

                $rate = new WC_Shipping_Rate();
                $rate->set_id($rateId);
                $rate->set_method_id($order_item->get_method_id());
                $rate->set_instance_id($order_item->get_instance_id());
                $rate->set_label($rateLabel);
                $rate->set_cost($rateCost);

                if (!empty($postedRate['meta_data'])) {
                    foreach ($postedRate['meta_data'] as $key => $value) {
                        $key = sanitize_key($key);
                        $value = sanitize_text_field($value);

                        $rate->add_meta_data($key, $value);
                        $order_item->add_meta_data($key, $value, true);
                    }
                }

                $order_item->set_shipping_rate($rate);
                $order_item->save();
            }
        }

        $order->calculate_totals();

        wp_send_json_success();

        die();
    }

    /**
     * Save posted pickup point data to current present Boekuwzending method
     * @noinspection PhpUnused
     */
    public static function savePickUpPointInformation(): void
    {
        check_ajax_referer('buz_wc', 'security');

        $pickUpPointPostData = $_POST['pick_up_point'];

        if (empty($pickUpPointPostData)) {
            wp_send_json_error(null, 400);
        }

        $pickUpPoint = boekuwzendingSanitizePostedPickUpPoint($pickUpPointPostData);

        $orderId = absint(wp_unslash($_POST['orderId']));
        $order = wc_get_order($orderId);

        $data = (object)[];
        $data->pick_up_point = $pickUpPoint;

        $order->add_meta_data('_boekuwzending_data', $data, true);
        $order->save();

        wp_send_json_success();

        die();
    }

    /**
     *
     */
    public static function getServices(): void
    {
        check_ajax_referer('buz_wc', 'security');

        $postItem = $_POST['item'];
        $order = new WC_Order(sanitize_text_field($postItem['orderId']));

        $shipment = new Shipment();
        $shipment->setTransportType(Shipment::TRANSPORT_TYPE_ROAD);

        $parsedAddress = boekuwzendingParseAddressLine($order->get_shipping_address_1().' '.$order->get_shipping_address_2());

        $shipToAddress = new Address();
        $shipToAddress->setStreet($parsedAddress->street);
        $shipToAddress->setNumber($parsedAddress->number);
        $shipToAddress->setNumberAddition($parsedAddress->numberAddition);
        $shipToAddress->setPostcode($order->get_shipping_postcode());
        $shipToAddress->setCity($order->get_shipping_city());
        $shipToAddress->setCountryCode($order->get_shipping_country());

        $dispatch = new DispatchInstruction();
        $dispatch->setDate((new DateTime())->modify('tomorrow'));
        $shipment->setDispatch($dispatch);

        $shipment->setShipToAddress($shipToAddress);
        $shipment->setTransportType(Shipment::TRANSPORT_TYPE_ROAD);

        $shipToContact = new Contact();
        $shipToContact->setName($order->get_formatted_shipping_full_name());
        $shipToContact->setCompany($order->get_shipping_company());
        $shipToContact->setEmailAddress($order->get_billing_email());
        $shipment->setShipToContact($shipToContact);

        $item = new Item();
        $item->setDescription('Item');
        $item->setType(sanitize_text_field($postItem['package_type']));
        $item->setQuantity((int)sanitize_text_field($postItem['quantity']));

        $itemWeight = $postItem['weight'] ?: self::$settingsHelper->getDefaultWeight();
        $itemLength = $postItem['length'] ?: self::$settingsHelper->getDefaultLength();
        $itemWidth = $postItem['width'] ?: self::$settingsHelper->getDefaultWidth();
        $itemHeight = $postItem['height'] ?: self::$settingsHelper->getDefaultHeight();

        $item->setWeight((float)sanitize_text_field($itemWeight));
        $item->setLength((float)sanitize_text_field($itemLength));
        $item->setWidth((float)sanitize_text_field($itemWidth));
        $item->setHeight((float)sanitize_text_field($itemHeight));

        $shipment->setItems([$item]);

        $client = self::$apiHelper->getApiClient();
        $rates = [];

        foreach ($client->rates->request($shipment) as $rate) {
            $rates[] = [
                'id' => $rate->getService()->getCode(),
                'description' => $rate->getService()->getDescription(),
                'price' => $rate->getPrice()
            ];
        }

        echo wp_send_json($rates);
        die();
    }

    /**
     * Add Boekuwzending metadata to hidden items
     * @noinspection PhpUnused
     *
     * @param array $items
     * @return array
     */
    public static function hideShippingMethodMetaData(array $items): array
    {
        $items[] = '_buz';
        $items[] = '_buz_id';
        $items[] = '_method';
        $items[] = '_pick_up';
        $items[] = '_distributor';
        $items[] = '_service_id';

        return $items;
    }

    /**
     * Add Boekuwzending metadata to hidden items
     * @noinspection PhpUnused
     *
     * @param $id
     * @param WC_Order_Item $item
     * @noinspection PhpUnusedParameterInspection
     */
    public static function addShippingMethodInformation($id, WC_Order_Item $item): void
    {
        if ($item->get_type() !== 'shipping') {
            return;
        }

        $showBoekuwzendingInformation = wc_string_to_bool($item->get_meta('_buz')) === true;
        $showPickupPointInformation = $showBoekuwzendingInformation && wc_string_to_bool(
                $item->get_meta('_pick_up')
            );

        if ($showBoekuwzendingInformation) {
            $data = self::$dataHelper->getBoekuwzendingData($item->get_order_id());

            if ($showPickupPointInformation) {
                /** @noinspection PhpUnusedLocalVariableInspection */
                $pickUpPoint = !empty($data->pick_up_point) ? $data->pick_up_point : [];

                $informationTemplate = apply_filters(
                    'boekuwzending_template_information_before_order_itemmeta',
                    Plugin::getTemplatePath('checkout/pick_up_point/information')
                );

                if (file_exists($informationTemplate)) {
                    require_once $informationTemplate;
                }
            }
        }
    }

    /**
     * Add Boekuwzending shipment method widget
     * @noinspection PhpUnused
     *
     * @param $id
     * @param WC_Order_Item $item
     * @noinspection PhpUnusedParameterInspection
     */
    public static function addShippingMethodWidget($id, WC_Order_Item $item): void
    {
        $order = $item->get_order();

        if ($item->get_type() !== 'shipping' || $order->is_editable() === false) {
            return;
        }

        $orderId = $order->get_id();
        $shipments = self::$dataHelper->getBoekuwzendingShipments($orderId);

        if (!empty($shipments)) {
            return;
        }

        $orderHasBoekuwzendingMethod = boekuwzendingOrderHasShippingMethod($item->get_order());
        $hasBoekuwzendingMetadata = wc_string_to_bool($item->get_meta('_buz')) === true;
        $showPickupPointInformation = $hasBoekuwzendingMetadata && wc_string_to_bool(
                $item->get_meta('_pick_up')
            );

        if ($orderHasBoekuwzendingMethod) {
            /** @noinspection PhpPossiblePolymorphicInvocationInspection */
            $displayDelivery = $item->get_method_id() === 'buz_wc_shipping_method_delivery' ? 'block' : 'none';

            if (!empty($hasBoekuwzendingMetadata)) {
                echo sprintf(
                    esc_html__('%sChoose a different shipping method%s', 'boekuwzending-for-woocommerce'),
                    '<a href="#" data-action="change_shipping_method" data-id="' . $item->get_id() . '" style="display: ' . $displayDelivery . ';">',
                    '</a>'
                );
            } else {
                echo sprintf(
                    esc_html__('%sChoose a shipping method%s', 'boekuwzending-for-woocommerce'),
                    '<a href="#" data-action="change_shipping_method" data-id="' . $item->get_id() . '" style="display: ' . $displayDelivery . ';">',
                    '</a>'
                );
            }

            /** @noinspection PhpPossiblePolymorphicInvocationInspection */
            $displayPickup = $item->get_method_id() === 'buz_wc_shipping_method_pickuppoint' ? 'block' : 'none';

            $data = self::$dataHelper->getBoekuwzendingData($orderId);

            if (!empty($data) && !empty($showPickupPointInformation)) {
                echo sprintf(
                    esc_html__('%sChoose a different Pick up point%s', 'boekuwzending-for-woocommerce'),
                    '<a href="#" data-action="choose_pickup_point" data-distributor="' . $item->get_meta('_distributor') . '" data-id="' . $item->get_id() . '" style="display: ' . $displayPickup . ';">',
                    '</a>'
                );
            } else {
                echo sprintf(
                    esc_html__('%sChoose Pick up point%s', 'boekuwzending-for-woocommerce'),
                    '<a href="#" data-action="choose_pickup_point" data-distributor="' . $item->get_meta('_distributor') . '" data-id="' . $item->get_id() . '" style="display: ' . $displayPickup . ';">',
                    '</a>'
                );
            }
        }
    }

    /**
     * Add services modal to output
     */
    public static function renderCreateShipmentModal(): void
    {
        $current_screen = get_current_screen();

        if ((!$current_screen || ($current_screen->id !== 'shop_order' && $current_screen->id !== 'edit-shop_order')) || $current_screen->base !== 'edit' || !is_admin()) {
            return;
        }

        require_once Plugin::getTemplatePath('admin/order/edit/modal/get-shipping-services');
    }

    /**
     * Add plugin action links
     * @param array $links
     * @return array
     * @noinspection PhpUnused
     */
    public static function addPluginActionLinks(array $links): array
    {
        $action_links = [
            '<a href="' . self::$settingsHelper->getGlobalSettingsUrl() . '">' . __(
                'Boekuwzending settings',
                'boekuwzending-for-woocommerce'
            ) . '</a>',
        ];

        // Add link to WooCommerce logs
        $action_links[] = '<a href="' . self::$settingsHelper->getLogsUrl() . '">' . __(
                'Logs',
                'boekuwzending-for-woocommerce'
            ) . '</a>';

        // Add link to Boekuwzending WooCommerce documentation
        $action_links[] = '<a href="' . self::$settingsHelper->getDocsUrl() . '" target="_blank">' . __(
                'Online Documentation',
                'boekuwzending-for-woocommerce'
            ) . '</a>';

        return array_merge($action_links, $links);
    }

    private static function getPostType($id) {
        if(class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
            return \Automattic\WooCommerce\Utilities\OrderUtil::get_order_type($id);
        }

        return get_post_type($id);
    }
}