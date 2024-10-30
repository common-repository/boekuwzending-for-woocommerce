import './../scss/settings.scss';

const $ = window.jQuery;

$(document).ready(function() {
    $('#boekuwzending_wc_sync_orders').on('change', function() {
        const show = !$(this).is(':checked');

        $('#boekuwzending_wc_matrices_enabled, ' +
            '#boekuwzending_wc_shipments_on_payment_enabled, ' +
            '#boekuwzending_wc_default_weight, ' +
            '#boekuwzending_wc_default_height, ' +
            '#boekuwzending_wc_default_width,' +
            '#boekuwzending_wc_default_length').closest('tr').toggle(show)

        $('#boekuwzending_wc_sync_orders_status').closest('tr').toggle(!show)
    }).trigger('change');
});