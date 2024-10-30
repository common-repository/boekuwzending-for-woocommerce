<?php

/**
 * Plugin Name: Boekuwzending for WooCommerce
 * Plugin URI: https://www.boekuwzending.com/
 * Description: Use the Boekuwzending Shipping Tool
 * Version: 2.3.3
 * Author: Boekuwzending.com
 * Author URI: https://www.boekuwzending.com
 * Requires at least: 5.0
 * Tested up to: 6.5.4
 * License: GPLv2 or later
 * Text Domain: boekuwzending-for-woocommerce
 * Domain Path: /languages
 * WC requires at least: 5.0.0
 * WC tested up to: 7.3.0
 */

use Boekuwzending\Utils\CompatibilityChecker;
use Boekuwzending\WooCommerce\Helper\Status;
use Boekuwzending\WooCommerce\Plugin;

if (!defined('WPINC')) {
    die;
}

define('BUZ_WC_FILE', __FILE__);
define('BUZ_WC_PLUGIN_DIR', dirname(BUZ_WC_FILE));

// Plugin folder URL.
if (!defined('BUZ_WC_PLUGIN_URL')) {
    define('BUZ_WC_PLUGIN_URL', plugin_dir_url(BUZ_WC_FILE));
}

/**
 * Called when plugin is activated
 */
function buz_wc_plugin_activation_hook()
{
    require_once __DIR__ . '/includes/functions.php';

    if (!buz_autoload()) {
        return;
    }

    if (!buz_wc_check_compatibility()) {
        add_action('admin_notices', 'buz_wc_plugin_inactive');
        return;
    }

    $status_helper = Plugin::getStatusHelper();

    if (!$status_helper->isCompatible()) {
        $title = __('An error occured', 'boekuwzending-for-woocommrce');

        $message = sprintf(
                __('%sCould not activate plugin%s%s'),
                '<h1><strong>',
                Plugin::PLUGIN_TITLE,
                '</strong></h1><br />'
            )
            . implode('<br/>', $status_helper->getErrors());

        wp_die($message, $title, array('back_link' => true));
    }
}

function buz_wc_check_compatibility()
{
    $wooCommerceVersion = get_option('woocommerce_version');
    $isWooCommerceVersionCompatible = version_compare(
        $wooCommerceVersion,
        Status::MIN_WOOCOMMERCE_VERSION,
        '>='
    );

    return class_exists('WooCommerce') && $isWooCommerceVersionCompatible;
}

function buz_wc_plugin_inactive_json_extension()
{
    if (!is_admin()) {
        return false;
    }

    echo '<div class="error"><p>';
    echo sprintf(
        esc_html__(
            '%s requires the JSON extension for PHP. Enable it on your server or ask your webhoster to enable it for you.',
            'boekuwzending-for-woocommerce'
        ),
        Plugin::PLUGIN_TITLE
    );
    echo '</p></div>';

    return false;
}

function buz_wc_plugin_inactive_php()
{
    if (!is_admin()) {
        return false;
    }

    echo '<div class="error"><p>';
    echo sprintf(
        esc_html__(
            '%s requires PHP %s or higher. Your PHP version is outdated.',
            'boekuwzending-for-woocommerce'
        ),
        Plugin::PLUGIN_TITLE,
        CompatibilityChecker::MIN_PHP_VERSION
    );
    echo '</p></div>';

    return false;
}

function buz_wc_plugin_inactive()
{
    if (!is_admin()) {
        return false;
    }

    if (!is_plugin_active('woocommerce/woocommerce.php')) {
        echo '<div class="error"><p>';
        echo sprintf(
            esc_html__(
                '%s%s is inactive.%s The %sWooCommerce plugin%s must be active for it to work. Please %sinstall & activate WooCommerce &raquo;%s',
                'boekuwzending-for-woocommerce'
            ),
            '<strong>',
            Plugin::PLUGIN_TITLE,
            '</strong>',
            '<a href="https://wordpress.org/plugins/woocommerce/">',
            '</a>',
            '<a href="' . esc_url(admin_url('plugins.php')) . '">',
            '</a>'
        );
        echo '</p></div>';
        return false;
    }

    if (version_compare(get_option('woocommerce_version'), Status::MIN_WOOCOMMERCE_VERSION, '<')) {
        echo '<div class="error"><p>';
        echo sprintf(
            esc_html__(
                '%1$s%s is inactive.%2$s This version requires WooCommerce %s or newer. Please %3$supdate WooCommerce to version %s or newer &raquo;%4$s',
                'boekuwzending-for-woocommerce'
            ),
            '<strong>',
            Plugin::PLUGIN_TITLE,
            Status::MIN_WOOCOMMERCE_VERSION,
            '</strong>',
            '<a href="' . esc_url(admin_url('plugins.php')) . '">',
            Status::MIN_WOOCOMMERCE_VERSION,
            '</a>'
        );
        echo '</p></div>';
        return false;
    }

    return true;
}

function buz_autoload()
{
    $autoloader = __DIR__ . '/vendor/autoload.php';

    if (file_exists($autoloader)) {
        /** @noinspection PhpIncludeInspection */
        require $autoloader;
    }

    return class_exists(Plugin::class);
}

$bootstrap = Closure::bind(
    static function () {
        add_action(
            'plugins_loaded',
            static function () {
                require_once __DIR__ . '/includes/functions.php';

                if (!buz_autoload()) {
                    return;
                }

                if (function_exists('extension_loaded') && !extension_loaded('json')) {
                    add_action('admin_notices', 'buz_wc_plugin_inactive_json_extension');
                    return;
                }

                if (version_compare(PHP_VERSION, CompatibilityChecker::MIN_PHP_VERSION, '<')) {
                    add_action('admin_notices', 'buz_wc_plugin_inactive_php');
                    return;
                }

                if (!buz_wc_check_compatibility()) {
                    add_action('admin_notices', 'buz_wc_plugin_inactive');
                    return;
                }

                add_action(
                    'init',
                    static function () {
                        load_plugin_textdomain(
                            'boekuwzending-for-woocommerce',
                            false,
                            dirname(plugin_basename(__FILE__)) . '/languages/'
                        );

                        Plugin::init();
                    }
                );
            }
        );
    },
    null
);

$bootstrap();

add_action('before_woocommerce_init', function(){
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
});

register_activation_hook(BUZ_WC_FILE, 'buz_wc_plugin_activation_hook');
