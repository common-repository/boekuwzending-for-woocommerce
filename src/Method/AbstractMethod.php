<?php

namespace Boekuwzending\WooCommerce\Method;

use Boekuwzending\WooCommerce\Helper\Api;
use Boekuwzending\WooCommerce\Plugin;
use WC_Shipping_Method;

/**
 * Class AbstractMethod
 */
abstract class AbstractMethod extends WC_Shipping_Method implements MethodInterface
{
    public const METHOD_PREFIX = 'buz_wc_shipping_method';

    /**
     * @var Api
     */
    protected $apiClient;

    /**
     * AbstractMethod constructor.
     * @param int $instance_id
     */
    public function __construct($instance_id = 0)
    {
        $this->supports = array(
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        );

        $this->plugin_id = '';
        $this->id = self::generateId(get_class($this));
        $this->instance_id = absint($instance_id);

        $this->method_title = sprintf('Boekuwzending - %s', $this->getDefaultTitle());
        $this->method_description = $this->getSettingsDescription();

        parent::__construct($instance_id);

        if (Plugin::getApiHelper()->isIntegrated()) {
            $this->apiClient = Plugin::getApiHelper()->getApiClient();
        }

        $this->init();
    }

    /**
     * @return void
     */
    public function init(): void
    {
        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables.
        $this->title = $this->get_option('title');
        $this->tax_status = $this->get_option('tax_status');

        // Actions.
        add_action('woocommerce_update_options_shipping_' . $this->id, [$this, 'process_admin_options']);
    }

    /**
     * @param string $className
     * @return string
     */
    public static function generateId(string $className): string
    {
        $path = explode('\\', $className);

        return sprintf('%s_%s', self::METHOD_PREFIX, strtolower(array_pop($path)));
    }

    /**
     * Init form fields.
     */
    public function init_form_fields(): void
    {
        $this->instance_form_fields = [
            'title' => [
                'title' => _x('Title', 'Shipping Method', 'boekuwzending-for-woocommerce'),
                'type' => 'text',
                'description' => _x(
                    'This controls the title which the user sees during checkout.',
                    'Shipping Method',
                    'boekuwzending-for-woocommerce'
                ),
                'default' => $this->get_method_title(),
                'desc_tip' => true,
            ],
            'tax_status' => [
                'title' => _x('Tax status', 'Shipping Method', 'boekuwzending-for-woocommerce'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'default' => 'taxable',
                'options' => [
                    'taxable' => _x('Taxable', 'Shipping Method', 'boekuwzending-for-woocommerce'),
                    'none' => _x('None', 'Tax status', 'boekuwzending-for-woocommerce'),
                ],
            ],
        ];
    }

    /**
     * @param $costs
     * @return float
     */
    public function sanitize_costs($costs): float
    {
        $locale         = localeconv();
        $decimals       = [wc_get_price_decimal_separator(), $locale['decimal_point'], $locale['mon_decimal_point'], ','];
        $costs = str_replace( $decimals, '.', $costs );

        return (float) $costs;
    }
}