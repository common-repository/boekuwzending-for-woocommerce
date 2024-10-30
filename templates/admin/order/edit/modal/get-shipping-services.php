<script type="text/template" id="tmpl-buz-wc-modal-get-shipping-services">
    <div class="wc-backbone-modal">
        <div class="wc-backbone-modal-content">
            <section class="wc-backbone-modal-main" role="main">
                <header class="wc-backbone-modal-header">
                    <h1><?php use Boekuwzending\WooCommerce\Helper\Settings;

                        esc_html_e('Give packaging details', 'boekuwzending-for-woocommerce'); ?></h1>
                    <button class="modal-close modal-close-link dashicons dashicons-no-alt">
                        <span class="screen-reader-text">Close modal panel</span>
                    </button>
                </header>
                <article>
                    <form id="get_services_form" action="" method="post">
                        <div style="display: flex;">
                            <div>
                                <label for="quantity"><?php echo __('Total packages', 'boekuwzending-for-woocommerce') ?></label>
                                <div>
                                    <input type="text" name="quantity" id="quantity" style="width: 100px;" value="1"/>
                                </div>
                            </div>
                            <div style="padding-left: 10px;">
                                <label for="package_type"><?php echo __('Package Type', 'boekuwzending-for-woocommerce') ?></label>
                                <div>
                                    <select name="package_type" id="package_type">
                                        <option value="package"><?php echo __('Package', 'boekuwzending-for-woocommerce') ?></option>
                                        <option value="pallet-euro"><?php echo __('Pallet', 'boekuwzending-for-woocommerce') ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div style="display: flex; margin-top: 10px;">
                            <div>
                                <label for="weight"><?php echo __('Total weight', 'boekuwzending-for-woocommerce') ?></label>
                                <div>
                                    <input type="text" name="weight" id="weight" style="width: 100px;" value="<?=get_option(Settings::PREFIX.'_default_weight')?>"/>
                                </div>
                            </div>
                            <div style="padding-left: 10px;">
                                <label for="height"><?php echo __('Height', 'boekuwzending-for-woocommerce') ?></label>
                                <div>
                                    <input type="text" name="height" id="height" style="width: 100px;" value="<?=get_option(Settings::PREFIX.'_default_height')?>"/>
                                </div>
                            </div>
                            <div style="padding-left: 10px;">
                                <label for="width"><?php echo __('Width', 'boekuwzending-for-woocommerce') ?></label>
                                <div>
                                    <input type="text" name="width" id="width" style="width: 100px;" value="<?=get_option(Settings::PREFIX.'_default_width')?>"/>
                                </div>
                            </div>
                            <div style="padding-left: 10px;">
                                <label for="length"><?php echo __('Length', 'boekuwzending-for-woocommerce') ?></label>
                                <div>
                                    <input type="text" name="length" id="length" style="width: 100px;" value="<?=get_option(Settings::PREFIX.'_default_length')?>"/>
                                </div>
                            </div>
                        </div>

                        <div style="margin-top: 10px;">
                            <button id="retrieve-rates"
                                    class="button button-primary button-large"><?php esc_html_e('Get Services', 'boekuwzending-for-woocommerce'); ?></button>
                        </div>

                        <table class="widefat" style="margin-top: 10px;">
                            <thead>
                            <tr>
                                <th><?php esc_html_e('Service', 'boekuwzending-for-woocommerce'); ?></th>
                                <th><?php esc_html_e('Price', 'boekuwzending-for-woocommerce'); ?></th>
                            </tr>
                            </thead>
                            <tbody id="modal_shipping_method_results"></tbody>
                        </table>
                    </form>
                </article>
                <footer>
                    <div class="inner">
                        <button id="btn-ok"
                                class="button button-primary button-large"><?php esc_html_e('Add', 'boekuwzending-for-woocommerce'); ?></button>
                    </div>
                </footer>
            </section>
        </div>
    </div>
    <div class="wc-backbone-modal-backdrop modal-close"></div>
</script>