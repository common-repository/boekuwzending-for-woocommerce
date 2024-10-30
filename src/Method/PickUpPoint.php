<?php

namespace Boekuwzending\WooCommerce\Method;

use Boekuwzending\Exception\AuthorizationFailedException;
use Boekuwzending\Exception\RequestFailedException;
use Boekuwzending\WooCommerce\Plugin;

/**
 * Class PickUpPoint
 */
class PickUpPoint extends AbstractMethod
{
    /**
     * @return string
     */
    public function getDefaultTitle(): string
    {
        return __('Pick Up Point', 'boekuwzending-for-woocommerce');
    }

    /**
     * @return string
     */
    public function getSettingsDescription(): string
    {
        return '';
    }

    /**
     * Calculate local pickup shipping.
     *
     * @param array $package Package information.
     * @throws AuthorizationFailedException
     * @throws RequestFailedException
     */
    public function calculate_shipping($package = array()): void
    {
        $isUsingMatrices = Plugin::getSettingsHelper()->isUsingMatrices();

        if (!$isUsingMatrices) {
            return;
        }

        $country = $package['destination']['country'];
        $postcode = boekuwzendingSanitizePostcode($package['destination']['postcode']);

        if (empty($postcode) || empty($country)) {
            return;
        }

        $shipment = boekuwzendingCreateShipment($package);

        $matrix = Plugin::getApiHelper()->getMatrix($shipment);

        if (null !== $matrix) {
            foreach ($matrix->getRates() as $rate) {
                // @todo: needs to be replaced with proper api filters
                if (!$rate->getService()->isPickupPoint()) {
                    continue;
                }

                $this->add_rate(
                    [
                        'id' => $rate->getService()->getKey(),
                        'label' => $rate->getService()->getName(),
                        'cost' => $rate->getPrice(),
                        'meta_data' => [
                            '_service_id' => $rate->getService()->getKey(),
                            '_buz' => true,
                            '_pick_up' => true,
                            '_address' => [
                                'country' => $country,
                                'postcode' => $postcode,
                            ],
                            '_distributor' => $rate->getService()->getDistributorIdentifier()
                        ]
                    ]
                );
            }
        }
    }
}