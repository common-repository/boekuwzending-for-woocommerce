<?php

use Boekuwzending\WooCommerce\Plugin;

if (!function_exists('is_order_received_page')) {
    /**
     * Check if the current page is the order received page
     *
     * @return bool
     * @since WooCommerce 2.3.3
     */
    function is_order_received_page()
    {
        global $wp;

        return is_page(wc_get_page_id('checkout')) && isset($wp->query_vars['order-received']);
    }
}

/**
 * Check if the current page context is for cart
 *
 * @return bool
 */
function boekuwzendingWooCommerceIsCartContext()
{
    return is_cart();
}

/**
 * Check if the current page context is for checkout
 *
 * @return bool
 */
function boekuwzendingWooCommerceIsCheckoutContext()
{
    return is_checkout();
}

/**
 * Check if the current page context is thank you page
 *
 * @return bool
 */
function boekuwzendingWooCommerceIsThankYouContext()
{
    return (boekuwzendingWooCommerceIsCheckoutContext() && !empty(is_wc_endpoint_url('order-received')));
}

/**
 * @return WC_Session|WC_Session_Handler|null
 */
function boekuwzendingWooCommerceSession()
{
    return WC()->session;
}

/**
 * Returns the order notes HTML so it can be used in XHR results
 *
 * @param $orderId
 * @return string
 */
function boekuwzendingWooCommerceGetOrderNotesHtml($orderId): string
{
    $notes = wc_get_order_notes(['order_id' => $orderId]);

    return wc_get_template_html('admin/meta-boxes/views/html-order-notes.php', [
        'notes' => $notes
    ], '', WC()->plugin_path() . '/includes/');
}

/**
 * Isolates static debug calls.
 *
 * @param string $message
 * @param bool $set_debug_header Set X-Boekuwzending-Debug header (default false)
 */
function boekuwzendingWooCommerceDebug($message, $set_debug_header = false)
{
    Plugin::debug($message, $set_debug_header);
}

/**
 * Isolates static addNotice calls.
 *
 * @param string $message
 * @param string $type One of notice, error or success (default notice)
 * // */
function boekuwzendingWooCommerceNotice($message, $type = 'notice')
{
    Plugin::addNotice($message, $type);
}