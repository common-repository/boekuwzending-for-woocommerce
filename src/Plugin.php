<?php
/** @noinspection PhpIncludeInspection */

namespace Boekuwzending\WooCommerce;

use Boekuwzending\Exception\AuthorizationFailedException;
use Boekuwzending\Exception\RequestFailedException;
use Boekuwzending\Resource\Address;
use Boekuwzending\Resource\Contact;
use Boekuwzending\Resource\DispatchInstruction;
use Boekuwzending\Resource\Item;
use Boekuwzending\Resource\Label;
use Boekuwzending\Resource\Order;
use Boekuwzending\Resource\OrderContact;
use Boekuwzending\Resource\OrderLine;
use Boekuwzending\Resource\PickupPoint as PickupPointResource;
use Boekuwzending\Resource\Shipment;
use Boekuwzending\Resource\TrackAndTrace;
use Boekuwzending\Utils\CompatibilityChecker;
use Boekuwzending\WooCommerce\Admin;
use Boekuwzending\WooCommerce\Helper\Api;
use Boekuwzending\WooCommerce\Helper\Data;
use Boekuwzending\WooCommerce\Helper\Notice;
use Boekuwzending\WooCommerce\Helper\Settings;
use Boekuwzending\WooCommerce\Helper\Status;
use Boekuwzending\WooCommerce\Method\AbstractMethod;
use Boekuwzending\WooCommerce\Method\Delivery;
use Boekuwzending\WooCommerce\Method\PickUpPoint;
use DateTime;
use Exception;
use RuntimeException;
use WC_Order;
use WC_Session;
use WC_Shipping_Rate;

/**
 * Class Plugin
 */
class Plugin
{
    public const PLUGIN_ID = 'boekuwzending-for-woocommerce';
    public const PLUGIN_TITLE = 'Boekuwzending for WooCommerce';
    public const PLUGIN_VERSION = '2.2.0';

    /**
     * @var bool
     */
    private static $initialized = false;

    /**
     * Initialize plugin
     */
    public static function init(): void
    {
        if (self::$initialized) {
            /*
             * Already initialized
             */
            return;
        }

        $settingsHelper = self::getSettingsHelper();
        $dataHelper = self::getDataHelper();
        $apiHelper = self::getApiHelper();
        $noticeHelper = self::getNoticeHelper();

        Admin\Hooks::init($settingsHelper, $dataHelper, $apiHelper, $noticeHelper);
        Admin\Metabox\Order::init($settingsHelper, $apiHelper, $dataHelper);

        // Add shipping methods
        add_filter('woocommerce_shipping_methods', [__CLASS__, 'addMethods']);

        // Register callback method
        add_action('woocommerce_api_boekuwzending_label_created', [__CLASS__, 'onWebhookActionLabelCreated']);
        add_action('woocommerce_api_boekuwzending_label_updated', [__CLASS__, 'onWebhookActionLabelUpdated']);

        add_action('woocommerce_checkout_create_order', [__CLASS__, 'addShippingInformationToOrder'], 10, 2);
        add_action('woocommerce_checkout_update_order_meta', [__CLASS__, 'clearSessionData'], 10, 2);

        add_filter('woocommerce_order_shipping_to_display', [__CLASS__, 'addPickUpPointToThankYouPage'], 10, 3);

        add_filter('woocommerce_payment_complete', [__CLASS__, 'createShipmentOnOrderPaid'], 10, 3);

        add_action('woocommerce_order_status_changed', [__CLASS__, 'syncOrder'], 10, 3);

        // Enqueue Scripts
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueueFrontendScripts']);

        // Render Pick up points html elements
        add_action('woocommerce_after_shipping_rate', [__CLASS__, 'renderPickupPointElements'], 10, 2);

        // Register ajax actions
        add_action('wp_ajax_boekuwzending_save_pick_up_point', [__CLASS__, 'savePickUpPointInWooCommerceSession']);
        add_action('wp_ajax_nopriv_boekuwzending_save_pick_up_point', [__CLASS__, 'savePickUpPointInWooCommerceSession']);

        add_action('wp_ajax_boekuwzending_get_rates', [__CLASS__, 'fetchRates']);
        add_action('wp_ajax_nopriv_boekuwzending_get_rates', [__CLASS__, 'fetchRates']);

        add_action('wp_ajax_boekuwzending_get_delivery_rates', [__CLASS__, 'fetchDeliveryRates']);
        add_action('wp_ajax_nopriv_boekuwzending_get_delivery_rates', [__CLASS__, 'fetchDeliveryRates']);

        add_action('wp_ajax_boekuwzending_get_pickup_rates', [__CLASS__, 'fetchPickupRates']);
        add_action('wp_ajax_nopriv_boekuwzending_get_pickup_rates', [__CLASS__, 'fetchPickupRates']);

        // Have `wc_get_orders()` support 'meta_query'. This may break when WooCommerce starts storing its metadata elsewhere.
        add_filter( 'woocommerce_get_wp_query_args', function( $wp_query_args, $query_vars ){
            if ( isset( $query_vars['meta_query'] ) ) {
                $meta_query = $wp_query_args['meta_query'] ?? [];
                $wp_query_args['meta_query'] = array_merge( $meta_query, $query_vars['meta_query'] );
            }
            return $wp_query_args;
        }, 10, 2 );

        self::registerScripts();
        self::registerFrontendScripts();

        // Mark plugin initiated
        self::$initialized = true;

        $noticeHelper::render();
    }

    /**
     * Register Scripts
     *
     * @return void
     */
    public static function registerScripts(): void
    {
        wp_register_script(
            'buz_wc_pickup_points_loader',
            self::getPluginUrl('/public/pickup-points-loader.min.js'),
            [],
            filemtime(self::getPluginPath('/public/pickup-points-loader.min.js')),
            true
        );

        wp_register_style(
            'buz_wc_pickup_points_loader',
            self::getPluginUrl('/public/pickup-points-loader.min.css'),
            [],
            filemtime(self::getPluginPath('/public/pickup-points-loader.min.css')),
            'screen'
        );
    }

    /**
     * Register Scripts
     *
     * @return void
     */
    public static function registerFrontendScripts(): void
    {
        wp_register_style(
            'buz_wc_checkout',
            self::getPluginUrl('/public/checkout.min.css'),
            ['buz_wc_pickup_points_loader'],
            filemtime(self::getPluginPath('/public/checkout.min.css')),
            'screen'
        );

        wp_register_script(
            'buz_wc_checkout',
            self::getPluginUrl('/public/checkout.min.js'),
            ['buz_wc_pickup_points_loader'],
            filemtime(self::getPluginPath('/public/checkout.min.js')),
            true
        );
    }

    /**
     * Enqueue Frontend only scripts
     *
     * @noinspection PhpUnused
     * @return void
     */
    public static function enqueueFrontendScripts(): void
    {
        if (is_admin() || (!boekuwzendingWooCommerceIsCheckoutContext())) {
            return;
        }

        $token = null;

        try {
            $token = self::getApiHelper()->getApiClient()->authorize(
                ['pickup_point']
            );
        } catch (AuthorizationFailedException | RuntimeException $e) {
            self::logException(__METHOD__, $e);
        }

        if ($token !== null) {
            // TODO: BUZ_API_URL override for dev
            $url = 'https://mijn.boekuwzending.com/pickup-points';

            wp_localize_script(
                'buz_wc_checkout',
                'buz_wc_settings',
                [
                    'url' => $url,
                    'token' => $token,
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'ajax_nonce' => wp_create_nonce('buz_wc'),
                ]
            );

            wp_enqueue_style('buz_wc_checkout');
            wp_enqueue_script('buz_wc_checkout');
        }
    }

    /**
     * @param array $methods
     * @return array
     * @noinspection PhpUnused
     */
    public static function addMethods(array $methods): array
    {
        $methods = array_merge(
            $methods,
            [
                AbstractMethod::generateId(Delivery::class) => Delivery::class,
                AbstractMethod::generateId(PickUpPoint::class) => PickUpPoint::class,
            ]
        );

        // Return if function get_current_screen() is not defined
        if (!function_exists('get_current_screen')) {
            return $methods;
        }

        // Try getting get_current_screen()
        $current_screen = get_current_screen();

        // Return if get_current_screen() isn't set
        if (!$current_screen) {
            return $methods;
        }

        return $methods;
    }

    /**
     * @param WC_Shipping_Rate $method
     * @noinspection PhpUnused
     */
    public static function renderPickupPointElements(WC_Shipping_Rate $method): void
    {
        if (
            boekuwzendingMethodIsPickupPoint($method)
            && boekuwzendingWooCommerceIsCheckoutContext()
            && boekuwzendingPickupPointActive($method)
        ) {
            $session = boekuwzendingWooCommerceSession();
            $metadata = $method->get_meta_data();

            $isCurrent = false;

            if ($session instanceof WC_Session) {
                $pickUpPoint = $session->get('buz_wc_pick_up_point');
                $isCurrent = !empty($metadata['_distributor']) && !empty($pickUpPoint['distributor']) && $pickUpPoint['distributor']['code'] === $metadata['_distributor'];

                if ($isCurrent) {
                    $informationTemplate = apply_filters(
                        'boekuwzending_template_checkout_pick_up_point_information',
                        self::getTemplatePath('checkout/pick_up_point/information')
                    );

                    if (file_exists($informationTemplate)) {
                        require_once $informationTemplate;
                    }
                }
            }

            $triggerTemplate = apply_filters(
                'boekuwzending_template_checkout_pick_up_point_trigger',
                self::getTemplatePath('checkout/pick_up_point/trigger')
            );

            if (file_exists($triggerTemplate)) {
                require_once $triggerTemplate;
            }
        }
    }

    /**
     * Save the posted data to WooCommerce session
     * @noinspection PhpUnused
     */
    public static function savePickUpPointInWooCommerceSession(): void
    {
        check_ajax_referer('buz_wc', 'security');

        $session = boekuwzendingWooCommerceSession();

        if ($session instanceof WC_Session) {
            $pickUpPointPostData = $_POST['pick_up_point'];

            if (empty($pickUpPointPostData)) {
                wp_send_json_error(null, 400);
            }

            $pickUpPoint = boekuwzendingSanitizePostedPickUpPoint($pickUpPointPostData);

            $session->set('buz_wc_pick_up_point', $pickUpPoint);
        }

        die();
    }

    /**
     * Fetch available delivery rates based on postcode and country from post data
     * @noinspection PhpUnused
     */
    public static function fetchDeliveryRates(): void
    {
        check_ajax_referer('buz_wc', 'security');

        if (empty($_POST['destination'])) {
            wp_send_json_error(null, 400);
        }

        $destination = boekuwzendingSanitizePostedDestination($_POST['destination']);
        $shipment = boekuwzendingCreateShipment(['destination' => $destination]);

        $matrix = self::getApiHelper()->getMatrix($shipment);
        $rates = [];

        if (null !== $matrix) {
            foreach ($matrix->getRates() as $rate) {
                //temp
                if ($rate->getService()->isPickupPoint()) {
                    continue;
                }

                $rates[] = boekuwzendingTransformDeliveryRate($rate);
            }
        }

        wp_send_json_success($rates);

        die();
    }

    /**
     * Fetch available pickup rates based on postcode and country from post data
     * @noinspection PhpUnused
     */
    public static function fetchPickupRates(): void
    {
        check_ajax_referer('buz_wc', 'security');

        if (empty($_POST['destination'])) {
            wp_send_json_error(null, 400);
        }

        $destination = boekuwzendingSanitizePostedDestination($_POST['destination']);
        $shipment = boekuwzendingCreateShipment(['destination' => $destination]);

        $matrix = self::getApiHelper()->getMatrix($shipment);
        $rates = [];

        if (null !== $matrix) {
            foreach ($matrix->getRates() as $rate) {
                //temp
                if ($rate->getService()->isPickupPoint() === false) {
                    continue;
                }

                $rates[] = boekuwzendingTransformPickupRate(
                    $rate,
                    $shipment->getShipToAddress()->getPostcode(),
                    $shipment->getShipToAddress()->getCountryCode()
                );
            }
        }

        wp_send_json_success($rates);

        die();
    }

    /**
     * Fetch available rates based on postcode and country from post data
     * @noinspection PhpUnused
     */
    public static function fetchRates(): void
    {
        check_ajax_referer('buz_wc', 'security');

        if (empty($_POST['destination'])) {
            wp_send_json_error(null, 400);
        }

        $destination = boekuwzendingSanitizePostedDestination($_POST['destination']);
        $shipment = boekuwzendingCreateShipment(['destination' => $destination]);

        $matrix = self::getApiHelper()->getMatrix($shipment);
        $rates = [];

        if (null !== $matrix) {
            foreach ($matrix->getRates() as $rate) {
                if ($rate->getService()->isPickupPoint()) {
                    $rates[] = boekuwzendingTransformPickupRate(
                        $rate,
                        $shipment->getShipToAddress()->getPostcode(),
                        $shipment->getShipToAddress()->getCountryCode()
                    );
                } else {
                    $rates[] = boekuwzendingTransformDeliveryRate($rate);
                }
            }
        }

        wp_send_json_success($rates);

        die();
    }

    /**
     * @param WC_Order $order
     *
     * @noinspection PhpUnused
     */
    public static function addShippingInformationToOrder(WC_Order $order): void
    {
        $activeShippingMethod = boekuwzendingGetActiveShippingMethod();

        if ($activeShippingMethod && boekuwzendingMethodIsBoekuwzending($activeShippingMethod)) {
            $data = (object)[];
            $session = boekuwzendingWooCommerceSession();

            if ($session instanceof WC_Session) {
                if (
                    boekuwzendingMethodIsPickupPoint($activeShippingMethod)
                    && !empty($session->get('buz_wc_pick_up_point'))) {
                    $data->pick_up_point = $session->get('buz_wc_pick_up_point');
                }

                $order->add_meta_data('_boekuwzending_data', $data);
            }
        }
    }

    /**
     * Clear all session data related to shipment
     * @noinspection PhpUnused
     */
    public static function clearSessionData(): void
    {
        $session = boekuwzendingWooCommerceSession();

        if ($session instanceof WC_Session) {
            $session->set('buz_wc_pick_up_point', null);
        }
    }


    /**
     * Get the BUZ-pickup shipping method if a pickup point was used.
     * @noinspection PhpUnused
     *
     * @param string $shipping
     * @param mixed $order
     * @return string
     */
    public static function addPickUpPointToThankYouPage(string $shipping, $order): string
    {
	    if(!$order instanceof WC_Order) {
		    return $shipping;
	    }

        $data = self::getDataHelper()->getBoekuwzendingData($order->get_id());

        if (!empty($data->pick_up_point)) {
            ob_start();

            /** @noinspection PhpUnusedLocalVariableInspection */
            $pickUpPoint = $data->pick_up_point;

            $informationTemplate = apply_filters(
                'boekuwzending_template_thank_you_pick_up_point_information',
                self::getTemplatePath('thank_you/information_pick_up_point')
            );

            if (file_exists($informationTemplate)) {
                require_once $informationTemplate;
            }

            $pickUpPointHtml = ob_get_clean();

            $shipping .= $pickUpPointHtml;
        }

        return $shipping;
    }

	private static function getTotalOrderWeight(array $allItems): float
	{
		reset($allItems);

		$totalWeight = array_reduce($allItems, static function ($prevWeight, $item) {
			$product = $item->get_product();

			return $prevWeight + ((int)$item->get_quantity() * (int)$product->get_weight());
		}, 0);

		// Ensure at least 100 grams.
		return max($totalWeight, 0.1);
	}

	private static function mapShipmentToMeta(Shipment $shipment): array
    {
        return [
            'id' => $shipment->getId(),
            'sequence' => $shipment->getSequence(),
            'labels' => array_map(
                static function (Label $label) {
                    return [
                        'id' => $label->getId(),
                        'waybill' => $label->getWaybill(),
                        'trackAndTraceLink' => $label->getTrackAndTraceLink()
                    ];
                },
                $shipment->getLabels()
            )
        ];
    }

    private static function sendAdminErrorMail(Exception $exception, string $orderId, string $errorType): void
    {
        if (!self::getSettingsHelper()->isAdminErrorMailEnabled()) {
            return;
        }

        $adminMail = get_bloginfo('admin_email');
        if (!$adminMail) {
            return;
        }
        $mailBody = 'An '.$errorType.' error occurred when we tried to forward WooCommerce order #'.$orderId.' to the Boekuwzending.com platform:'."\n\n".$exception;

        wp_mail($adminMail, '[Boekuwzending WooCommerce] '.$errorType.' error processing order #' . $orderId, $mailBody, $headers = '');
    }

    /**
     * @param WC_Order $order
     * @return int|null
     */
    public static function createShipment(WC_Order $order, array $itemData = [], string $service = null): ?int
    {
        $apiClient = self::getApiHelper()->getApiClient();

        /** @var array $shipments */
        $shipments = self::getDataHelper()->getBoekuwzendingShipments($order->get_id());

        $related = null;
        if (is_array($shipments)) {
            $related = current($shipments)['id'];

            self::debug(sprintf('Creating additional label for order %s', $order->get_id()));
        } else {
            $shipments = [];

            self::debug(sprintf('Creating shipment for order %s', $order->get_id()));
        }

        $shipment = new Shipment();

        if (null !== $service) {
            $shipment->setService($service);
        } else {
            $shippingService = boekuwzendingGetShippingMethodForOrder($order);
var_dump($shippingService);
            if (null !== $shippingService) {
                $shipment->setService($shippingService->get_meta('_service_id'));

                if (wc_string_to_bool($shippingService->get_meta('_pick_up')) === true) {
                    $pickupData = $order->get_meta('_boekuwzending_data')->pick_up_point;

                    $pickupAddress = $pickupData['address'];

                    $pickupPoint = new PickupPointResource();
                    $pickupPoint->setIdentifier($pickupData['identifier']);
                    $pickupPoint->setDistributorCode($pickupData['distributor']['code']);
                    $pickupPoint->setName($pickupData['name']);
                    $pickupPoint->setCountry($pickupAddress['country']);
                    $pickupPoint->setPostcode($pickupAddress['postcode']);
                    $pickupPoint->setStreet($pickupAddress['street']);
                    $pickupPoint->setNumber($pickupAddress['number']);
                    $pickupPoint->setCity($pickupAddress['city']);

                    $shipment->setPickupPoint($pickupPoint);
                }
            }
        }

        $shipToContact = new Contact();
        $shipToContact->setName(
            implode(' ', [$order->get_shipping_first_name(), $order->get_shipping_last_name()])
        );
        $shipToContact->setCompany($order->get_shipping_company());
        $shipToContact->setEmailAddress($order->get_billing_email());

        if (!empty($order->get_billing_phone())) {
            $shipToContact->setPhoneNumber($order->get_billing_phone());
        }

        $parsedAddress = boekuwzendingParseAddressLine($order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2());

        $shipToAddress = new Address();
        $shipToAddress->setStreet($parsedAddress->street);
        $shipToAddress->setNumber($parsedAddress->number);
        $shipToAddress->setNumberAddition($parsedAddress->numberAddition);
        $shipToAddress->setPostcode($order->get_shipping_postcode());
        $shipToAddress->setCity($order->get_shipping_city());
        $shipToAddress->setCountryCode($order->get_shipping_country());
        $shipToAddress->setPostcode($order->get_shipping_postcode());
        $shipToAddress->setPrivateAddress(empty($order->get_shipping_company()));

        $dispatch = new DispatchInstruction();
        $dispatch->setDate((new DateTime())->modify('tomorrow'));

        $defaultItemWeight = self::getSettingsHelper()->getDefaultWeight();
        $defaultItemLength = self::getSettingsHelper()->getDefaultLength();
        $defaultItemWidth = self::getSettingsHelper()->getDefaultWidth();
        $defaultItemHeight = self::getSettingsHelper()->getDefaultHeight();

        $shipmentItems = [];
        if (!empty($itemData)) {
            $shipmentItem = new Item();
            $shipmentItem->setQuantity($itemData['quantity']);
            $shipmentItem->setType($itemData['package_type']);
            $shipmentItem->setLength((float) $itemData['length'] ?: $defaultItemLength);
            $shipmentItem->setHeight((float) $itemData['height'] ?: $defaultItemHeight);
            $shipmentItem->setWidth((float) $itemData['width'] ?: $defaultItemWidth);
            $shipmentItem->setWeight((float) $itemData['weight'] ?: $defaultItemWeight);
            $shipmentItem->setDescription('Pakket');

            $shipmentItems[] = $shipmentItem;
        } else {
			$allItems = $order->get_items();
            $item = current($allItems);

            /** @noinspection PhpPossiblePolymorphicInvocationInspection */
            $product = $item->get_product();

            $shipmentItem = new Item();
            $shipmentItem->setQuantity(1);
            $shipmentItem->setType(Item::TYPE_PACKAGE);
            $shipmentItem->setLength((float) $product->get_length() ?: $defaultItemLength);
            $shipmentItem->setHeight((float) $product->get_height() ?: $defaultItemHeight);
            $shipmentItem->setWidth((float) $product->get_width() ?: $defaultItemWidth);
            $shipmentItem->setWeight( self::getTotalOrderWeight($allItems) ?: $defaultItemWeight);
            $shipmentItem->setDescription('Pakket');
            $shipmentItem->setValue((float) $product->get_price());

            $shipmentItems[] = $shipmentItem;
        }

        $shipment->setShipToAddress($shipToAddress);
        $shipment->setshipToContact($shipToContact);
        $shipment->setItems($shipmentItems);
        $shipment->setTransportType(Shipment::TRANSPORT_TYPE_ROAD);
        $shipment->setDispatch($dispatch);
        $shipment->setInvoiceReference($order->get_order_number());

        $shipment->setRelated($related);

        try {
            $shipmentToCreate = apply_filters('boekuwzending_create_shipment', $shipment, $order);
            $shipment = $apiClient->shipment->create($shipmentToCreate);

            $shipments[$shipment->getId()] = self::mapShipmentToMeta($shipment);

            $order->add_meta_data('_boekuwzending_shipments', $shipments, true);

            if (empty($related)) {
                $order->add_order_note(
                    sprintf(
                        esc_html__('Boekuwzending &ndash; Shipment created (%s).', 'boekuwzending-for-woocommerce'),
                        $shipment->getSequence()
                    ),
                    0,
                    true
                );

                self::debug(
                    sprintf(
                        'Shipment created for order %s with number %s',
                        $order->get_id(),
                        $shipment->getSequence()
                    )
                );
            } else {
                $order->add_order_note(
                    esc_html__('Boekuwzending &ndash; Additional label created.', 'boekuwzending-for-woocommerce'),
                    0,
                    true
                );

                self::debug(sprintf('Additional label created for order %s', $order->get_id()));
            }

            return $order->save();
        } catch (AuthorizationFailedException $e) {
            self::logException(__METHOD__, $e);

            self::getNoticeHelper()::admin(
                esc_html__(
                    'Could not authenticate with the Boekuwzending API, check the debug logs for more information.',
                    'boekuwzending-for-woocommerce'
                ),
                'error',
                true
            );

            self::sendAdminErrorMail($e, $order->get_id(), 'authentication');
        } catch (RequestFailedException $e) {
            self::logException(__METHOD__, $e);

            self::getNoticeHelper()::admin(
                esc_html__(
                    'An error occurred while creating the shipment, check the debug logs for more information.',
                    'boekuwzending-for-woocommerce'
                ),
                'error',
                true
            );

            self::sendAdminErrorMail($e, $order->get_id(), 'unexpected');
        }

        return null;
    }

    /**
     * Reads the _boekuwzending_shipments meta to detect BUZ Shipments, then requests the labels
     * for those shipments and updates their status in the _boekuwzending_status meta.
     *
     * @param WC_Order $order
     */
    public static function retrieveStatus(WC_Order $order): void
    {
        try {
            $client = self::getApiHelper()->getApiClient();

            $shipments = $order->get_meta('_boekuwzending_shipments');
            $labelMeta = [];

            foreach ($shipments as $shipment) {
                foreach ($shipment['labels'] as $label) {
                    $trackAndTrace = $client->trackAndTrace->get($label['id']);

                    if (!$trackAndTrace instanceof TrackAndTrace) {
                        continue;
                    }

                    $activeStatus = $trackAndTrace->getActive();

                    if ($activeStatus) {
                        $labelMeta[$label['id']]['status'] = $activeStatus->getStatus();
                    }
                }
            }

            $order->add_meta_data('_boekuwzending_status', $labelMeta, true);
            $order->save();
        } catch (AuthorizationFailedException | RequestFailedException $e) {
            self::logException(__METHOD__, $e);
        }
    }

    /**
     * @param $shipmentId
     * @throws AuthorizationFailedException
     * @throws RequestFailedException
     */
    public static function downloadLabels(WC_Order $order): void
    {
        $client = self::getApiHelper()->getApiClient();

        $shipments = array_map(function ($shipment) {
            return $shipment['id'];
        }, $order->get_meta('_boekuwzending_shipments'));

        $response = $client->label->download($shipments);

        header('Content-Disposition: attachment; filename=labels.pdf');
        header('Content-Type: application/force-download');
        header('Content-Type: application/octet-stream');
        header('Content-Type: application/download');

        echo $response;
        exit;
    }

    /**
     * @param $shipmentId
     * @throws AuthorizationFailedException
     * @throws RequestFailedException
     */
    public static function retrieveLabels($shipmentId): void
    {
        $client = self::getApiHelper()->getApiClient();

        $response = $client->shipment->downloadLabels($shipmentId);

        header('Content-Disposition: attachment; filename=' . $shipmentId . '.pdf');
        header('Content-Type: application/force-download');
        header('Content-Type: application/octet-stream');
        header('Content-Type: application/download');

        echo $response;
        exit;
    }

    /**
     * Add a WooCommerce notification message
     *
     * @param string $message Notification message
     * @param string $type One of notice, error or success (default notice)
     */
    public static function addNotice($message, $type = 'notice'): void
    {
        $type = in_array($type, ['notice', 'error', 'success']) ? $type : 'notice';

        wc_add_notice($message, $type);
    }

    /**
     * Log messages to WooCommerce log
     *
     * @param mixed $message
     * @param bool $set_debug_header Set X-Boekuwzending-Debug header (default false)
     */
    public static function debug($message, $set_debug_header = false): void
    {
        // Convert message to string
        if (!is_string($message)) {
            $message = wc_print_r($message, true);
        }

        // Set debug header
        if ($set_debug_header && PHP_SAPI !== 'cli' && !headers_sent()) {
            header("X-Boekuwzending-Debug: $message");
        }

        // Log message
        if (self::getSettingsHelper()->isDebugEnabled()) {
            $logger = wc_get_logger();

            $context = array('source' => self::PLUGIN_ID . '-' . date('Y-m-d'));

            $logger->debug($message, $context);
        }
    }

    public static function logException(string $method, \Exception $exception): void
    {
        // Set debug header
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            header("X-Boekuwzending-Debug: error");
        }

        if (false === self::getSettingsHelper()->isDebugEnabled()) {
            return;
        }

        $logger = wc_get_logger();

        $message = sprintf('%s: %s', $method, $exception);

        $context = array('source' => self::PLUGIN_ID . '-' . date('Y-m-d'));

        $logger->error($message, $context);
    }

    /**
     * Get location of main plugin file
     *
     * @return string
     */
    public static function getPluginFile(): string
    {
        return plugin_basename(self::PLUGIN_ID . '/' . self::PLUGIN_ID . '.php');
    }

    /**
     * Get plugin URL
     *
     * @param string $path
     * @return string
     */
    public static function getPluginUrl($path = ''): string
    {
        return untrailingslashit(BUZ_WC_PLUGIN_URL) . '/' . ltrim($path, '/');
    }

    /**
     * Get plugin path
     *
     * @param string $path
     * @return string
     */
    public static function getPluginPath($path = ''): string
    {
        return untrailingslashit(BUZ_WC_PLUGIN_DIR) . '/' . ltrim($path, '/');
    }

    /**
     * Get path to template file
     *
     * @param $template
     * @return string
     */
    public static function getTemplatePath($template): string
    {
        return untrailingslashit(BUZ_WC_PLUGIN_DIR) . '/templates/' . ltrim($template, '/') . '.php';
    }

    /**
     * @return Settings
     */
    public static function getSettingsHelper(): Settings
    {
        static $settings_helper;

        if (!$settings_helper) {
            $settings_helper = new Settings();
        }

        return $settings_helper;
    }


    /**
     * @return Api
     */
    public static function getApiHelper(): Api
    {
        static $api_helper;

        if (!$api_helper) {
            $api_helper = new Api(self::getSettingsHelper());
        }

        return $api_helper;
    }


    /**
     * @return Notice
     */
    public static function getNoticeHelper(): Notice
    {
        static $notice_helper;

        if (!$notice_helper) {
            $notice_helper = new Notice();
        }

        return $notice_helper;
    }

    /**
     * @return Data
     */
    public static function getDataHelper(): Data
    {
        static $data_helper;

        if (!$data_helper) {
            $data_helper = new Data(self::getApiHelper());
        }

        return $data_helper;
    }

    /**
     * @return Status
     */
    public static function getStatusHelper(): Status
    {
        static $status_helper;

        if (!$status_helper) {
            $status_helper = new Status(new CompatibilityChecker());
        }

        return $status_helper;
    }

    /**
     * @param string|int $orderId
     */
    public static function createShipmentOnOrderPaid($orderId): void
    {
        if (boekuwzendingCreateShipmentOnPayment()) {
            $order = self::getDataHelper()->getWcOrder($orderId);

            if ($order) {
                $meta = $order->get_meta('_boekuwzending_shipments');

                if (!is_array($meta)) {
                    self::createShipment($order);
                } else {
                    self::debug(sprintf('Shipment already exists for order %s', $orderId));
                }
            }
        }
    }

    /**
     * Process label updated webhook from Boekuwzending Platform.
     * @throws Exception
     */
    public static function onWebhookActionLabelUpdated(): void
    {
        self::debug(__METHOD__ . ":  entering webhook action." . __LINE__);

        self::onWebhookActionLabelCreated();
    }

    /**
     * Process label created webhook from Boekuwzending Platform.
     * @throws Exception
     */
    public static function onWebhookActionLabelCreated(): void
    {
        self::debug(__METHOD__ . ":  entering webhook action." . __LINE__);

        $json = file_get_contents('php://input');
        $decoded = json_decode($json, true);

        if (is_bool($decoded) || null === $decoded) {
            self::setHttpResponseCode(404);
            self::debug(__METHOD__ . ": Not possible to decode json.");

            return;
        }

        $settingsHelper = self::getSettingsHelper();
        $credentials = $settingsHelper->getClientCredentials();

        $apiClientId = $credentials['clientId'];
        $apiClientSecret = $credentials['clientSecret'];

        if (empty($apiClientId) || empty($apiClientSecret)) {
            self::setHttpResponseCode(404);
            self::debug(__METHOD__ . ": Client id or secret is null.");

            return;
        }

        $webhookData = $decoded['data'];
        $encodedPayload = json_encode($webhookData);

        if (false === $encodedPayload) {
            self::setHttpResponseCode(404);
            self::debug(__METHOD__ . ": Not possible to encode json.");

            return;
        }

        $hmac = hash_hmac('sha256', $encodedPayload, $apiClientId . $apiClientSecret);

        if ((false === hash_equals($hmac, $decoded['meta']['hmac'])) && 'true' !== getenv('DISABLE_HMAC_VALIDATION')) {
            self::setHttpResponseCode(400);
            self::debug(__METHOD__ . ": Invalid HMAC signature.");

            return;
        }

        $data_helper = self::getDataHelper();

        $reference = sanitize_text_field($webhookData['external_order_external_id']);

        if (!empty($reference)) {
            $order = $data_helper->getWcOrder($reference);

            if (!$order) {
                self::setHttpResponseCode(404);
                self::debug(sprintf('%s: Could not find order %s.', __METHOD__, $reference));

                return;
            }
        } else {
            $shipmentId = sanitize_text_field($webhookData['shipment_id']);

            // Find the order by Shipment ID, by LIKE-searching through the metadata.
            $orders = wc_get_orders(['meta_query' => [[
                'key' => '_boekuwzending_shipments',
                'compare' => 'like',
                'value' => $shipmentId,
            ]]]);

            $order = current($orders);

            if (!$order instanceof WC_Order) {
                self::setHttpResponseCode(404);
                self::debug(__METHOD__ . ": Could not find an order by shipment ID ".$shipmentId);

                return;
            }
        }

        $client = self::getApiHelper()->getApiClient();

        $shipmentsMeta = $order->get_meta('_boekuwzending_shipments');
        if (!is_array($shipmentsMeta)) {
            $shipmentsMeta = [];
        }

        // Retrieve the Shipment
        $shipmentId = $webhookData['shipment_id'];
        $shipment = $client->shipment->get($shipmentId);
        $shipmentsMeta[$shipmentId]= self::mapShipmentToMeta($shipment);
        $order->add_meta_data('_boekuwzending_shipments', $shipmentsMeta, true);

        // Only change the order status when that option is active.
        if ($settingsHelper->isOrderStatusChangeThroughWebhooksEnabled()) {
            $shippedStatus = $settingsHelper->getShippedOrderStatus();

            if ($shippedStatus !== $order->get_status()) {
                $order->update_status($shippedStatus, sprintf(__('Boekuwzending &ndash; Label Created: %s', 'boekuwzending-for-woocommerce'), $webhookData['tracking_number']));
            }
        }

        $labelMeta = $order->get_meta('_boekuwzending_status');

        if (!is_array($labelMeta)) {
            $labelMeta = [];
        }

        $labelId = $webhookData['entity_id'];

        if (isset($labelId)) {
            $labelMeta[$labelId] = [
                'tracking_number' => $webhookData['tracking_number'],
                'status' => $webhookData['status'] ?? 'created',
            ];

            $trackAndTrace = $client->trackAndTrace->get($labelId);

            if ($trackAndTrace instanceof TrackAndTrace) {
                $activeStatus = $trackAndTrace->getActive();
                if ($activeStatus) {
                    $labelMeta[$labelId]['status'] = $activeStatus->getStatus();
                }
            }

            self::debug(sprintf('%s: Saving Order metadata by Boekuwzending for waybill %s.', __METHOD__, $labelMeta[$labelId]['tracking_number']), true);

            $order->add_meta_data('_boekuwzending_status', $labelMeta, true);
        }

        $order->save();
    }

    /**
     * Set HTTP status code
     *
     * @param int $status_code
     */
    public static function setHttpResponseCode($status_code): void
    {
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            header("HTTP/1.0 ".$status_code);

            if (function_exists("http_response_code")) {
                http_response_code($status_code);
            }
        }
    }

	/**
	 * @param $orderId
	 *
	 * @throws Exception
	 */
    public static function syncOrder($orderId): void
    {
        $wcOrder = new WC_Order($orderId);

		if (!self::getDataHelper()->orderNeedsShipping($wcOrder)) {
			return;
		}

        if (!self::getSettingsHelper()->isSyncingOrdersEnabled()) {
            return;
        }

        $processingStatus = self::getSettingsHelper()->getSendOrderToPlatformStatus();

        if ($wcOrder->get_status() !== $processingStatus) {
            return;
        }

        self::createOrder($wcOrder);
    }

    public static function createOrder(WC_Order $wcOrder): Order
    {
        $order = new Order();
        $order->setExternalId($wcOrder->get_id());
        $order->setReference($wcOrder->get_id());
        $order->setCreatedAtSource(new DateTime());

        $contact = new OrderContact();
        $contact->setName($wcOrder->get_formatted_shipping_full_name());
        $contact->setCompany($wcOrder->get_shipping_company());
        $contact->setPhoneNumber($wcOrder->get_billing_phone());
        $contact->setEmailAddress($wcOrder->get_billing_email());

        $order->setShipToContact($contact);

        $parsedAddress = boekuwzendingParseAddressLine($wcOrder->get_shipping_address_1() . ' ' . $wcOrder->get_shipping_address_2());

        $address = new Address();
	    $address->setPrivateAddress(empty($wcOrder->get_shipping_company()));
        $address->setStreet($parsedAddress->street);
        $address->setNumber($parsedAddress->number);
        $address->setNumberAddition($parsedAddress->numberAddition);
        $address->setPostcode($wcOrder->get_shipping_postcode());
        $address->setCity($wcOrder->get_shipping_city());
        $address->setCountryCode($wcOrder->get_shipping_country());

        $order->setShipToAddress($address);

        $lines = [];
        foreach ($wcOrder->get_items() as $item) {
            $product = wc_get_product($item->get_product_id());

            $line = new OrderLine();
            $line->setExternalId($item->get_id());
            $line->setDescription($item->get_name());
            $line->setQuantity($item->get_quantity());
            $line->setValue($item->get_total());
            $line->setSkuNumber($product->get_sku());

            $lines[] = $line;
        }

        $order->setOrderLines($lines);

        $client = self::getApiHelper()->getApiClient();

        try {
            $orderToCreate = apply_filters('boekuwzending_create_order', $order, $wcOrder);
            $order = $client->order->create($orderToCreate);
        } catch (Exception $e) {
            self::sendAdminErrorMail($e, $wcOrder->get_id(), 'unexpected');

            throw $e;
        }

        $meta []= [
            'id' => $order->getId()
        ];

        $wcOrder->add_order_note(
            sprintf(
                esc_html__('Boekuwzending &ndash; Order created (%s).', 'boekuwzending-for-woocommerce'),
                $order->getId()
            ),
            0,
            true
        );

        $wcOrder->add_meta_data('_boekuwzending_orders', $meta, true);
        $wcOrder->save();

        return $order;
    }

    public static function translateLabelStatus(string $status): string
    {
        $englishStatus = self::getEnglishStatus($status);

        return _x($englishStatus, 'LabelStatus', 'boekuwzending-for-woocommerce');
    }

    private static function getEnglishStatus(string $status): string
    {
        switch ($status) {
            case 'new':
            case 'created':
                return 'New';
            case 'scanned':
                return 'Scanned';
            case 'in_transit':
                return 'In transit';
            case 'delivered':
                return 'Delivered';
            case 'neighbours':
                return 'Delivered at neighbours';
            case 'picked_up':
                return 'Picked up';
            case 'at_pickup_point':
                return 'At pickup point';
            case 'return_in_transit':
                return 'Return in transit';
            case 'return_delivered':
                return 'Return delivered';
            case 'at_customs':
                return 'At customs';
            case 'rejected':
                return 'Rejected';
        }

        return 'Unknown';
    }


}
