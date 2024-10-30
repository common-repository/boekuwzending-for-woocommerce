<?php

namespace Boekuwzending\WooCommerce\Helper;

use WC_Order;

/**
 * Class Data
 */
class Data
{
    /**
     * @var Api
     */
    protected $apiHelper;

    /**
     * @param Api $apiHelper
     */
    public function __construct(Api $apiHelper)
    {
        $this->apiHelper = $apiHelper;
    }

    /**
     * Get WooCommerce order
     *
     * @param int $order_id Order ID
     * @return WC_Order|bool
     */
    public function getWcOrder($order_id)
    {
        return wc_get_order($order_id);
    }

    /**
     * @param $order_id
     * @return array|mixed|string
     */
    public function getBoekuwzendingData($order_id)
    {
        $order = wc_get_order($order_id);

        if (!$order && isset($_GET['post'])) {
            $order = wc_get_order($_GET['post']);
        }

        if (!$order) {
            return '';
        }

        return $order->get_meta($order_id, '_boekuwzending_data', true);
    }

    /**
     * @param $order_id
     * @return array|mixed|string
     */
    public function getBoekuwzendingStatus($order_id)
    {
        $order = wc_get_order($order_id);

        if (!$order && isset($_GET['post'])) {
            $order = wc_get_order($_GET['post']);
        }

        if (!$order) {
            return '';
        }

        return $order->get_meta($order_id, '_boekuwzending_status', true);
    }

    /**
     * @param $order_id
     * @return array|mixed|string
     */
    public function getBoekuwzendingShipments($order_id)
    {
        $order = wc_get_order($order_id);

        if (!$order && isset($_GET['post'])) {
            $order = wc_get_order($_GET['post']);
        }

        if (!$order) {
            return '';
        }

        return $order->get_meta('_boekuwzending_shipments');
    }

    /**
     * @param $order_id
     * @return array|mixed|string
     */
    public function getBoekuwzendingOrders($order_id)
    {
        $order = wc_get_order($order_id);

        if (!$order && isset($_GET['post'])) {
            $order = wc_get_order($_GET['post']);
        }

        if (!$order) {
            return '';
        }

        return $order->get_meta('_boekuwzending_orders');
    }

	/**
	 * Returns <c>false</c> when an order exists of all virtual items (like licenses, downloads, ...).
	 */
	public function orderNeedsShipping(WC_Order $wcOrder): bool
	{
		foreach ($wcOrder->get_items() as $order_item) {
			$item = wc_get_product($order_item->get_product_id());

			if (!$item->is_virtual()) {
				return true;
			}
		}

		return false;
	}
}
