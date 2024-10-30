<?php

use Boekuwzending\Resource\Address;
use Boekuwzending\Resource\Contact;
use Boekuwzending\Resource\DispatchInstruction;
use Boekuwzending\Resource\Item;
use Boekuwzending\Resource\MatrixRate;
use Boekuwzending\Resource\Shipment;
use Boekuwzending\WooCommerce\Helper\Notice;
use Boekuwzending\WooCommerce\Plugin;

/**
 * @return bool
 */
function boekuwzendingCreateShipmentOnPayment(): bool {
    $settingsHelper = Plugin::getSettingsHelper();

    return $settingsHelper->createShipmentOnPaymentEnabled();
}

/**
 * @return bool
 */
function boekuwzendingIsUsingMatrices(): bool {
    $settingsHelper = Plugin::getSettingsHelper();

    return $settingsHelper->isUsingMatrices();
}

/**
 * @param WC_Shipping_Rate $method
 * @return bool
 */
function boekuwzendingMethodIsPickupPoint(WC_Shipping_Rate $method): bool
{
    $metadata = $method->get_meta_data();

    return (!empty($metadata) && array_key_exists(
            '_pick_up',
            $metadata
        ) && wc_string_to_bool($metadata['_pick_up']) === true);
}

/**
 * @param WC_Shipping_Rate $method
 * @return bool
 */
function boekuwzendingMethodIsBoekuwzending(WC_Shipping_Rate $method): bool
{
    $metadata = $method->get_meta_data();

    return (!empty($metadata) && array_key_exists(
            '_buz',
            $metadata
        ) && wc_string_to_bool($metadata['_buz']) === true);
}

/**
 * @param WC_Shipping_Rate $method
 * @return bool
 */
function boekuwzendingPickUpPointActive(WC_Shipping_Rate $method): bool
{
    return in_array($method->get_id(), wc_get_chosen_shipping_method_ids(), true);
}

/**
 * @return WC_Shipping_Rate|null
 */
function boekuwzendingGetActiveShippingMethod(): ?WC_Shipping_Rate
{
    $chosenShippingMethods = wc_get_chosen_shipping_method_ids();
    $shippingMethods = WC()->shipping()->get_shipping_methods();
    $method = null;

    foreach ($shippingMethods as $shippingMethod) {
        foreach ($shippingMethod->rates as $method) {
            if (!in_array($method->get_id(), $chosenShippingMethods, true)) {
                continue;
            }

            return $method;
        }
    }

    return null;
}

/**
 * @param WC_Order $order
 * @return bool
 */
function boekuwzendingOrderHasShippingMethod(WC_Order $order): bool
{
    foreach ($order->get_shipping_methods() as $shippingMethod) {
        if (strpos($shippingMethod->get_method_id(), 'buz_wc_shipping_method') !== false) {
            return true;
        }

        continue;
    }

    return false;
}

/**
 * @param WC_Order $order
 * @return WC_Shipping_Rate|null
 */
function boekuwzendingOrderGetShippingMethod(WC_Order $order): ?WC_Order_Item_Shipping
{
    foreach ($order->get_shipping_methods() as $shippingMethod) {
        if (strpos($shippingMethod->get_method_id(), 'buz_wc_shipping_method') !== false) {
            return $shippingMethod;
        }

        continue;
    }

    return null;
}

/**
 * @param WC_Order $order
 * @return bool
 */
function boekuwzendingOrderShippingMethodIsDelivery(WC_Order $order): bool
{
    foreach ($order->get_shipping_methods() as $shippingMethod) {
        if ($shippingMethod->get_method_id() === 'buz_wc_shipping_method_delivery') {
            return true;
        }

        continue;
    }

    return false;
}

/**
 * @param WC_Order $order
 * @return bool
 */
function boekuwzendingOrderShippingMethodIsPickupPoint(WC_Order $order): bool
{
    foreach ($order->get_shipping_methods() as $shippingMethod) {
        if ($shippingMethod->get_method_id() === 'buz_wc_shipping_method_pickuppoint') {
            return true;
        }

        continue;
    }

    return false;
}

/**
 * @param WC_Order $order
 * @return WC_Order_Item|null
 */
function boekuwzendingGetShippingMethodForOrder(WC_Order $order): ?WC_Order_Item
{
    foreach ($order->get_shipping_methods() as $shippingMethod) {
        if ($shippingMethod->get_meta('_buz')) {
            return $shippingMethod;
        }
    }

    return null;
}

/**
 * @param string $postcode
 * @return string
 */
function boekuwzendingSanitizePostcode(string $postcode): string
{
    return str_replace(' ', null, $postcode);
}

/**
 * Sanitize all values in an array and return it
 *
 * @param array $data
 * @return array
 */
function boekuwzendingSanitizeArray(array $data): array {
    $returnData = [];

    foreach ($data as $key => $value) {
        $returnData[sanitize_key($key)] = is_array($value) ? boekuwzendingSanitizePostedPickUpPoint($value) : sanitize_text_field($value);
    }

    return $returnData;
}

/**
 * Sanitize posted pick up point data and return it
 *
 * @param array $data
 * @return array
 */
function boekuwzendingSanitizePostedPickUpPoint(array $data): array {
    $pickUpPoint = [];

    foreach ($data as $key => $value) {
        $pickUpPoint[sanitize_key($key)] = is_array($value) ? boekuwzendingSanitizePostedPickUpPoint($value) : sanitize_text_field($value);
    }

    return $pickUpPoint;
}

/**
 * Sanitize posted destination data and return it
 *
 * @param array $data
 * @return array
 */
function boekuwzendingSanitizePostedDestination(array $data): array {
    $destination = [];

    foreach ($data as $key => $value) {
        $destination[sanitize_key($key)] = is_array($value) ? boekuwzendingSanitizePostedDestination($value) : sanitize_text_field($value);
    }

    return $destination;
}

/**
 * @param string $address
 * @return object
 */
function boekuwzendingParseAddressLine(string $address): object
{
    $number = null;
    $numberAddition = null;

    if (preg_match('/^\\s*(.+)\\s+(\\d+)\\s*(\\S*\\s+\\d+\\s*\\S*)$/', $address, $parts)
        || preg_match('/^\\s*(.+)\\s+(\\d+)\\s*(,\\s*.*)$/', $address, $parts)
        || preg_match('/^\\s*(.+)\\s+(\\d+)\\s*(.*)$/', $address, $parts)
    ) {
        $street = $parts[1];
        $number = $parts[2];
        $numberAddition = trim($parts[3]);
    } elseif (preg_match('/^\\s*(\\d+)(\\S*)\\s+(.*)$/', $address, $parts)) {
        $street = $parts[3];
        $number = $parts[1];
        $numberAddition = $parts[2];
    } elseif (preg_match('/^\\s*(.+\\D)\\s*(\\d+)\\s*(\\D+\\s*\\d*\\s*\\S*)$/', $address, $parts)
        || preg_match('/^\\s*(.+\\D)\\s*(\\d+)\\s*(.*)$/', $address, $parts)
    ) {
        $street = $parts[1];
        $number = $parts[2];
        $numberAddition = trim($parts[3]);
    } else {
        $street = $address;
    }

    return apply_filters('boekuwzending_parse_address_lines', (object)[
        'street' => $street,
        'number' => $number,
        'numberAddition' => trim($numberAddition, '-')
    ], $address);
}

/**
 * @param array $package
 * @return Shipment
 */
function boekuwzendingCreateShipment(array $package) {
    $settingsHelper = Plugin::getSettingsHelper();

    $shipment = new Shipment();
    $shipmentItems = [];

    $parsedAddress = boekuwzendingParseAddressLine($package['destination']['address']);

    $shipToContact = new Contact();
    $shipToContact->setName('Naam');
    $shipToContact->setEmailAddress('test@test.nl');
    $shipment->setShipToContact($shipToContact);

    $shipToAddress = new Address();
    $shipToAddress->setStreet($parsedAddress->street);
    $shipToAddress->setNumber($parsedAddress->number);
    $shipToAddress->setNumberAddition($parsedAddress->numberAddition);
    $shipToAddress->setPostcode($package['destination']['postcode']);
    $shipToAddress->setCity($package['destination']['city']);
    $shipToAddress->setCountryCode($package['destination']['country']);

    $dispatch = new DispatchInstruction();
    $dispatch->setDate((new DateTime())->modify('tomorrow'));
    $shipment->setDispatch($dispatch);

    $shipment->setShipToAddress($shipToAddress);
    $shipment->setTransportType(Shipment::TRANSPORT_TYPE_ROAD);

    foreach($package['contents'] as $item) {
        $shipmentItem = new Item();
        $product = $item['data'];

        $itemWeight = $product->get_weight() ?: $settingsHelper->getDefaultWeight();
        $itemLength = $product->get_length() ?: $settingsHelper->getDefaultLength();
        $itemWidth = $product->get_width() ?: $settingsHelper->getDefaultWidth();
        $itemHeight = $product->get_height() ?: $settingsHelper->getDefaultHeight();

        $shipmentItem->setDescription('Item');
        $shipmentItem->setType('package');
        $shipmentItem->setQuantity((int)sanitize_text_field($item['quantity']));
        $shipmentItem->setWeight((float)sanitize_text_field($itemWeight));
        $shipmentItem->setLength((float)sanitize_text_field($itemLength));
        $shipmentItem->setWidth((float)sanitize_text_field($itemWidth));
        $shipmentItem->setHeight((float)sanitize_text_field($itemHeight));
        $shipmentItem->setValue((float)sanitize_text_field($item['line_total']));

        $shipmentItems[] = $shipmentItem;
    }

    $shipment->setItems($shipmentItems);

    return $shipment;
}

/**
 * @param MatrixRate $rate
 * @return array
 */
function boekuwzendingTransformDeliveryRate(MatrixRate $rate): array {
    return [
        'id' => $rate->getService()->getKey(),
        'label' => $rate->getService()->getName(),
        'cost' => $rate->getPrice(),
        'meta_data' => [
            '_service_id' => $rate->getService()->getKey(),
            '_buz' => true,
            '_pick_up' => false
        ]
    ];
}

/**
 * @param MatrixRate $rate
 *
 * @param string $postcode
 * @param string $country
 * @return array
 */
function boekuwzendingTransformPickupRate(MatrixRate $rate, string $postcode, string $country): array {
    return [
        'id' => $rate->getService()->getKey(),
        'label' => $rate->getService()->getName(),
        'cost' => $rate->getPrice(),
        'meta_data' => [
            '_service_id' => $rate->getService()->getKey(),
            '_buz' => true,
            '_pick_up' => true,
            '_address' => [
                'country' => $country,
                'postcode' => boekuwzendingSanitizePostcode($postcode),
            ],
            '_distributor' => $rate->getService()->getDistributorIdentifier()
        ]
    ];
}

/**
 * Method to check if it is an actual XHR request instead of de is_ajax() method from WordPress
 *
 * @return bool
 */
function boekuwzendingIsAdminXHR(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower(
            $_SERVER['HTTP_X_REQUESTED_WITH']
        ) === 'xmlhttprequest';
}

/**
 * Check if there are any admin notices to show and send them with the error result
 */
function boekuwzendingSendJsonError() {
    $hasNotices = Notice::hasNotices();
    $errors = '';

    if ($hasNotices) {
        ob_start();

        Notice::render(true);

        $errors = ob_get_clean();
    }

    wp_send_json_error($errors, 400);
}
