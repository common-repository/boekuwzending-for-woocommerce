<script type="text/template" id="tmpl-buz-wc-modal-change-shipping-method">
    <div class="wc-backbone-modal">
        <div class="wc-backbone-modal-content">
            <section class="wc-backbone-modal-main" role="main">
                <header class="wc-backbone-modal-header">
                    <h1><?php esc_html_e( 'Choose a service', 'boekuwzending-for-woocommerce' ); ?></h1>
                    <button class="modal-close modal-close-link dashicons dashicons-no-alt">
                        <span class="screen-reader-text">Close modal panel</span>
                    </button>
                </header>
                <article>
                    <form action="" method="post">
                        <table class="widefat">
                            <thead>
                            <tr>
                                <th><?php esc_html_e( 'Service', 'boekuwzending-for-woocommerce' ); ?></th>
                                <th><?php esc_html_e( 'Price', 'boekuwzending-for-woocommerce' ); ?></th>
                            </tr>
                            </thead>
                            <tbody id="modal_shipping_method_results"></tbody>
                        </table>
                    </form>
                </article>
                <footer>
                    <div class="inner">
                        <button id="btn-ok" class="button button-primary button-large"><?php esc_html_e( 'Add', 'boekuwzending-for-woocommerce' ); ?></button>
                    </div>
                </footer>
            </section>
        </div>
    </div>
    <div class="wc-backbone-modal-backdrop modal-close"></div>
</script>