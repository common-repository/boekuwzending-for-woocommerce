<?php

extract($pickUpPoint, EXTR_OVERWRITE);

if (!empty($pickUpPoint)) :
    ?>

    <div class="boekuwzending__pick-up-point__information">
        <div class="boekuwzending__pick-up-point__information__icon" style="width: 16px; height: 16px;">
            <svg fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd"
                      d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z"
                      clip-rule="evenodd"></path>
            </svg>
        </div>

        <div class="boekuwzending__pick-up-point__information__wrapper">
            <div class="boekuwzending__pick-up-point__information__name">
                <?php
                echo $name; ?>
            </div>
            <div class="boekuwzending__pick-up-point__information__address">
                <div><?php
                    echo $address['street'] . ' ' . $address['number']; ?></div>
                <div><?php
                    echo $address['postcode'] . ' ' . $address['city']; ?></div>
            </div>
        </div>
    </div>

<?php
endif;
?>

