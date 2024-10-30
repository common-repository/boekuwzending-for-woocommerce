<?php

namespace Boekuwzending\WooCommerce\Settings;

use Boekuwzending\WooCommerce\Helper\Settings;
use Boekuwzending\WooCommerce\Plugin;
use WC_Admin_Settings;
use WC_Settings_Page;

/**
 * Class Page
 */
class Page extends WC_Settings_Page
{
    /**
     * @var Settings
     */
    protected $settingsHelper;

    /**
     * Page constructor.
     * @param Settings $settingsHelper
     */
    public function __construct(Settings $settingsHelper)
    {
        $this->id = Settings::PREFIX;
        $this->label = __('Boekuwzending', 'boekuwzending-for-woocommerce');

        $this->settingsHelper = $settingsHelper;

        wp_register_script(
            'boekuwzending_wc_admin_settings',
            Plugin::getPluginUrl('/public/admin/settings.min.js'),
            ['jquery'],
            filemtime(Plugin::getPluginPath('/public/admin/settings.min.js'))
        );

        wp_enqueue_script('boekuwzending_wc_admin_settings');

        wp_register_style(
            'boekuwzending_wc_admin_settings',
            Plugin::getPluginUrl('/public/admin/settings.min.css'),
            [],
            filemtime(Plugin::getPluginPath('/public/admin/settings.min.css'))
        );

        wp_enqueue_style('boekuwzending_wc_admin_settings');

        parent::__construct();
    }

    /**
     * @return void
     */
    public function output(): void
    {
        $settings = $this->get_settings();

        WC_Admin_Settings::output_fields($settings);
    }

    /**
     * @return array
     */
    public function get_settings(): array
    {
        $content = $this->settingsHelper->getPluginStatus();

        $clientId = null;
        $clientSecret = null;

        $clientIdOptions = [
            'type' => 'text',
            'id' => $this->generateSettingId('api_client_id'),
            'title' => _x('Client ID', 'Boekuwzending Settings', 'boekuwzending-for-woocommerce'),
        ];
        $clientSecretOptions = [
            'type' => 'text',
            'id' => $this->generateSettingId('api_client_secret'),
            'title' => _x('Client Secret', 'Boekuwzending Settings', 'boekuwzending-for-woocommerce'),
        ];

        if(isset($_GET['payload'])) {
            $payload = sanitize_text_field($_GET['payload']);

            if (!empty($payload) && is_string($payload)) {
                /** @noinspection JsonEncodingApiUsageInspection */
                $payload = json_decode(base64_decode($payload), true);

                $clientIdOptions['value'] = sanitize_text_field($payload['client_id']);
                $clientSecretOptions['value'] = sanitize_text_field($payload['client_secret']);
            }
        }

        $settings = [
            [
                'type' => 'title',
                'id' => $this->generateSettingId('main_title'),
                'title' => _x(
                    'Settings',
                    'Boekuwzending Settings',
                    'boekuwzending-for-woocommerce'
                ),
                'desc' => '<p>' . $content . '</p>'
            ],
            'api_client_id' => $clientIdOptions,
            'api_client_secret' => $clientSecretOptions,
            'sync_orders' => [
                'id' => $this->generateSettingId('sync_orders'),
                'title' => _x('Sync orders', 'Boekuwzending Settings', 'boekuwzending-for-woocommerce'),
                'default' => 'yes',
                'type' => 'checkbox',
                'desc_tip' => _x(
                    'Enable if you want to process orders from within the Mijn Boekuwzending platform. If enabled matrices and processing shipments on payments will be unavailable.',
                    'Boekuwzending Settings',
                    'boekuwzending-for-woocommerce'
                ),
            ],
            'sync_orders_status' => [
                'id' => $this->generateSettingId('sync_orders_status'),
                'title' => _x('Status for syncing', 'Boekuwzending Settings', 'boekuwzending-for-woocommerce'),
                'default' => 'wc-processing',
                'type' => 'select',
                'options' => wc_get_order_statuses()
            ],
            'label_webhook_change_order_status' => [
                'id' => $this->generateSettingId('label_webhook_change_order_status'),
                'title' => _x('Update order status on label creation', 'Boekuwzending Settings', 'boekuwzending-for-woocommerce'),
                'default' => 'yes',
                'type' => 'checkbox',
                'desc_tip' => _x(
                    'Enable if you want to move your orders to the status configured below when a label is created on the Mijn Boekuwzending platform.',
                    'Boekuwzending Settings',
                    'boekuwzending-for-woocommerce'
                ),
            ],
            'label_webhook_change_order_status_status' => [
                'id' => $this->generateSettingId('label_webhook_change_order_status_status'),
                'title' => _x('Status after label creation', 'Boekuwzending Settings', 'boekuwzending-for-woocommerce'),
                'default' => 'wc-completed',
                'type' => 'select',
                'options' => wc_get_order_statuses()
            ],
            'matrices_enabled' => [
                'id' => $this->generateSettingId('matrices_enabled'),
                'title' => _x('Enable Matrices', 'Boekuwzending Settings', 'boekuwzending-for-woocommerce'),
                'type' => 'checkbox',
                'default' => 'no',
                'desc_tip' => _x(
                    'Enable if you want to use your configured matrices instead of shipment costs calculated by WooCommerce.',
                    'Boekuwzending Settings',
                    'boekuwzending-for-woocommerce'
                ),
            ],
            'shipments_on_payment_enabled' => [
                'id' => $this->generateSettingId('shipments_on_payment_enabled'),
                'title' => _x('Create shipments on payment', 'Boekuwzending Settings', 'boekuwzending-for-woocommerce'),
                'type' => 'checkbox',
                'desc_tip' => _x(
                    'Enable if you want shipments to be automatically created on a successful payment. This feature is only available when matrices are enabled.',
                    'Boekuwzending Settings',
                    'boekuwzending-for-woocommerce'
                ),
            ],
            'default_weight' => [
                'id' => $this->generateSettingId('default_weight'),
                'title' => _x('Default weight', 'Boekuwzending Settings', 'boekuwzending-for-woocommerce'),
                'type' => 'text',
                'desc_tip' => _x(
                    'In Kilograms',
                    'Boekuwzending Settings',
                    'boekuwzending-for-woocommerce'
                )
            ],
            'default_height' => [
                'id' => $this->generateSettingId('default_height'),
                'title' => _x('Default height', 'Boekuwzending Settings', 'boekuwzending-for-woocommerce'),
                'type' => 'text',
                'desc_tip' => _x(
                    'In centimeters',
                    'Boekuwzending Settings',
                    'boekuwzending-for-woocommerce'
                )
            ],
            'default_width' => [
                'id' => $this->generateSettingId('default_width'),
                'title' => _x('Default width', 'Boekuwzending Settings', 'boekuwzending-for-woocommerce'),
                'type' => 'text',
                'desc_tip' => _x(
                    'In centimeters',
                    'Boekuwzending Settings',
                    'boekuwzending-for-woocommerce'
                )
            ],
            'default_length' => [
                'id' => $this->generateSettingId('default_length'),
                'title' => _x('Default length', 'Boekuwzending Settings', 'boekuwzending-for-woocommerce'),
                'type' => 'text',
                'desc_tip' => _x(
                    'In centimeters',
                    'Boekuwzending Settings',
                    'boekuwzending-for-woocommerce'
                )
            ],
            'debug' => [
                'id' => $this->generateSettingId('debug'),
                'title' => _x('Debug Log', 'Boekuwzending Settings', 'boekuwzending-for-woocommerce'),
                'type' => 'checkbox',
                'desc' => ' <a href="' . $this->settingsHelper->getLogsUrl() . '">' . _x(
                        'View logs',
                        'Boekuwzending Settings',
                        'boekuwzending-for-woocommerce'
                    ) . '</a>',
                'default' => 'yes',
            ],
            'admin_error_mail' => [
                'id' => $this->generateSettingId('admin_error_mail'),
                'title' => _x('Send email to admin on sync error', 'Boekuwzending Settings', 'boekuwzending-for-woocommerce'),
                'default' => 'yes',
                'type' => 'checkbox',
                'desc_tip' => _x(
                    'Enable if you want to receive an email when the plugin could not send an order or shipment to the Boekuwzending.com platform.',
                    'Boekuwzending Settings',
                    'boekuwzending-for-woocommerce'
                ),
            ],
            'section_end' => [
                'type' => 'sectionend',
                'id' => $this->generateSettingId('section_end')
            ]
        ];

        if (!Plugin::getApiHelper()->isIntegrated()) {
            if (!empty($payload)) {
                array_unshift(
                    $settings,
                    [
                        'type' => 'title',
                        'id' => $this->generateSettingId('integrated_title'),
                        'title' => '',
                        'desc' => sprintf(
                            esc_html__(
                                "%sLinking your Boekuwzending account was successful.%sDon't forget to save the WordPress settings with the button below.%s",
                                'boekuwzending-for-woocommerce'
                            ),
                            '<div id="message" class="fade notice notice-success">',
                            '<p><strong>',
                            '</strong></p></div>'
                        )
                    ]
                );
            } else {
                array_unshift(
                    $settings,
                    [
                        'type' => 'title',
                        'id' => $this->generateSettingId('integrated_title'),
                        'title' => '',
                        'desc' => sprintf(
                            esc_html__(
                                "%sYour Boekuwzending account is not yet connected, click %shere%s to link your Boekuwzending account.%s",
                                'boekuwzending-for-woocommerce'
                            ),
                            '<div id="message" class="notice notice-warning"><p>',
                            '<a href="https://mijn.boekuwzending.com/integration/woocommerce/install?redirectUrl=' . $this->settingsHelper->getGlobalSettingsUrl(
                            ) . '">',
                            '</a>',
                            '</p></div>'
                        )
                    ]
                );
            }
        }

        return $settings;
    }

    /**
     * @param string $key
     * @return string
     */
    private function generateSettingId(string $key): string
    {
        return sprintf('%s_%s', $this->id, $key);
    }
}
