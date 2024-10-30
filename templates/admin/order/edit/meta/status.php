<div class="status">
    <?php

    $labelCount = -1;
    if (!empty($shipments)) {
        $labelCount = 0;
        foreach ($shipments as $shipment) {
            $labelCount += count($shipment['labels']);
        }
    }

    if (0 === $labelCount) {
        _e('A shipment has been created for this order at Boekuwzending.com, but it has no labels yet.', 'boekuwzending-for-woocommerce');
    }

    if (!empty($shipments)) {
        foreach ($shipments as $shipment) {
            $labels = $shipment['labels'];
            foreach ($labels as $label) {
                $statusString = '';

                if (isset($status[$label['id']]['status'])) {
                    $statusString = Boekuwzending\WooCommerce\Plugin::translateLabelStatus($status[$label['id']]['status']);
                }

                $url = wp_nonce_url(
                    admin_url(
                        'admin-ajax.php?action=boekuwzending_admin_retrieve_labels&shipment_id=' . $shipment['id']
                    ),
                    'boekuwzending-retrieve-labels'
                );

                ?>
                <div class="status-line">
                    <div class="status-tracking-number"><?php
                        echo $label['waybill']; ?></div>
                    <div class="status-tracking-actions">
                        <a href="<?php echo $url; ?>" data-buz-tooltip="true" title="<?php _e('Download shipping label', 'boekuwzending-for-woocommerce'); ?>">download label</a> <a href="<?php echo $label['trackAndTraceLink']; ?>" data-buz-tooltip="true" title="<?php _e('Open Track and Trace website', 'boekuwzending-for-woocommerce'); ?>" target="_blank">track and trace</a>
                    </div>
                    <?php if (!empty($statusString)) : ?>
                    <div class="status-tracking-text"><?php
                        echo $statusString; ?></div>
                    <?php endif; ?>
                </div>

                <?php
            }
        }
    } elseif (!empty($orders)) {
        echo '<div>'
            . sprintf(
                __('This order has been sent to your <a href="%s" target="_blank">Imported Orders at Boekuwzending.com</a>', 'boekuwzending-for-woocommerce'),
                'https://mijn.boekuwzending.com/bestellingen/overzicht')
            . '<br/>'
            . '</div>';
    } else {
        _e('Your order will be exported once it reaches the configured status. You can bypass this and force an export using the button below.', 'boekuwzending-for-woocommerce');
    }

    ?>
</div>