<?php

extract($pickUpPoint, EXTR_OVERWRITE);

if (!empty($pickUpPoint)) :
    ?>

    <div class="boekuwzending__order-overview__pick-up-point">
        <div class="boekuwzending__order-overview__pick-up-point__icon">
            <svg fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd"
                      d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z"
                      clip-rule="evenodd"></path>
            </svg>
        </div>

        <div class="boekuwzending__order-overview__pick-up-point__wrapper">
            <?php echo sprintf('%s - %s %s, %s %s', $name, $address['street'], $address['number'], $address['postcode'], $address['city']); ?>
        </div>
    </div>


<?php
endif;
?>