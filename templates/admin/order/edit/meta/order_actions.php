<?php

$createOrderUrl = wp_nonce_url(
    admin_url('admin-ajax.php?action=boekuwzending_admin_create_order&screen=details&order_id=' . $order->get_id()),
    'boekuwzending-create-order'
);
?>

<ul class="boekuwzending_actions submitbox">
    <li class="wide">
<?php if (empty($shipments) && empty($orders)) : ?>
    <a href="<?php echo $createOrderUrl; ?>" class="button boekuwzending-create-order"
       data-action="create_order"><?php
        _e('Export to Boekuwzending.com', 'boekuwzending-for-woocommerce'); ?></a>
<?php elseif (!empty($shipments)) : ?>
        <a href="<?php
        echo $refreshStatusUrl; ?>" data-buz-tooltip="true" title="<?php
        _e('Retrieve Status', 'boekuwzending-for-woocommerce'); ?>" class="boekuwzending-refresh-status"
           data-action="retrieve_status">
            <svg viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd"
                      d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z"
                      clip-rule="evenodd"></path>
            </svg>
        </a>

        <a href="<?php
        echo $downloadLabelsUrl; ?>" data-buz-tooltip="true" title="<?php
        _e('Download Labels', 'boekuwzending-for-woocommerce'); ?>" class="boekuwzending-download-labels">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
        </a>
<?php endif; ?>

    <?php if (!empty($shipments)) : ?>
        <a href="<?php echo $createAdditionalLabelUrl; ?>" class="button boekuwzending-create-additional-label"
           data-action="create_additional_label"><?php
            _e('Create additional label', 'boekuwzending-for-woocommerce'); ?></a>
    <?php else : ?>
        <a href="<?php echo $createNewOrderUrl; ?>" class="button boekuwzending-create-order"
           data-action="create_order"><?php
            _e('Create new order', 'boekuwzending-for-woocommerce'); ?></a>
    <?php endif; ?>
    </li>
</ul>
