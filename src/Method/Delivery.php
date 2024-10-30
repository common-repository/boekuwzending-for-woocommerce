<?php

namespace Boekuwzending\WooCommerce\Method;

use Boekuwzending\WooCommerce\Plugin;

/**
 * Class Delivery
 */
class Delivery extends AbstractMethod
{
    /** @var float */
    protected $fallbackCost;

    public function init(): void
    {
        parent::init();

        $this->fallbackCost = $this->get_option('fallback_cost');
    }

    /**
     * @return string
     */
    public function getDefaultTitle(): string
    {
        return __('Delivery', 'boekuwzending-for-woocommerce');
    }

    /**
     * @return string
     */
    public function getSettingsDescription(): string
    {
        return '';
    }

    /**
     * Init form fields.
     */
    public function init_form_fields(): void
    {
        parent::init_form_fields();

        $this->instance_form_fields['fallback_cost'] = [
            'title' => __('Fallback Cost', 'boekuwzending-for-woocommerce'),
            'type' => 'text',
            'placeholder' => '0',
            'description' => __('Fallback cost for when there is no available rate from Boekuwzending Matrix.', 'boekuwzending-for-woocommerce'),
            'default' => '',
            'desc_tip' => true,
        ];
    }


    /**
     * Calculate local pickup shipping.
     *
     * @param array $package Package information.
     */
    public function calculate_shipping($package = array()): void
    {
        $isUsingMatrices = Plugin::getSettingsHelper()->isUsingMatrices();

        if (!$isUsingMatrices) {
            return;
        }

        $country = $package['destination']['country'];

        if (empty($country)) {
            return;
        }

        $shipment = boekuwzendingCreateShipment($package);

        $matrix = Plugin::getApiHelper()->getMatrix($shipment);

        if (null !== $matrix) {
            foreach ($matrix->getRates() as $rate) {
                //temp
                if ($rate->getService()->isPickupPoint()) {
                    continue;
                }

                $this->add_rate(boekuwzendingTransformDeliveryRate($rate));
            }
        }
    }
}