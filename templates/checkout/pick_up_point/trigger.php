<?php

$pickUpPointActive = !empty($pickUpPoint) && $isCurrent;

echo sprintf(
    '<div class="boekuwzending__pick-up-point__trigger"><a href="#" data-method="%s" data-action="buz_choose_pick_up_point" data-postcode="%s" data-country="%s" data-distributor="%s">%s</a></div>',
    $method->get_id(),
    $metadata['_address']['postcode'],
    $metadata['_address']['country'],
    $metadata['_distributor'],
    !$pickUpPointActive
        ?
        apply_filters(
            'boekuwzending_checkout_pick_up_point_trigger_choose_text',
            __('Choose Pick up point', 'boekuwzending-for-woocommerce')
        )
        :
        apply_filters(
            'boekuwzending_checkout_pick_up_point_trigger_change_text',
            __('Choose a different Pick up point', 'boekuwzending-for-woocommerce')
        )

);