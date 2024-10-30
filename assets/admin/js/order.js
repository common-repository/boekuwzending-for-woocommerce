import './../scss/order.scss';
import './../img/icon.png';

let modalMode, buzPickupPointLoader;
const addIdentifier = 'buz-wc-modal-add-shipping-method';
const changeIdentifier = 'buz-wc-modal-change-shipping-method';
const getServicesIdentifier = 'buz-wc-modal-get-shipping-services';
let orderId;

const $ = window.jQuery;

$(document).ready(function () {
    let currentOrderItemId = null;
    let rates = [];
    buzPickupPointLoader = initializePickupPointLoader();

    $('body')
        .on('wc_backbone_modal_loaded', (e, target) => {
            if (target === addIdentifier) {
                const {address, postcode, city, country} = getShippingAddressDetails();

                $.ajax({
                    method: 'POST',
                    url: buz_wc_settings.ajax_url,
                    data: {
                        action: getActionByMode(),
                        security: buz_wc_settings.ajax_nonce,
                        destination: {
                            address,
                            postcode,
                            city,
                            country
                        }
                    }
                }).done((response) => {
                    const {success, data} = response;

                    if (success === false) {
                        alert(buz_wc_settings.i18n_unknown_error_occured);
                    } else {
                        rates = data;
                        const rateElements = [];

                        data.forEach((rate) => {
                            rateElements.push(`
                                <tr>
                                    <td>
                                        <label>
                                            <input type="radio" name="service_id" value="${rate.id}" data-costs="${rate.cost}" data-meta_data="${JSON.stringify(rate.meta_data)}" /> ${rate.label}
                                        </label>
                                    </td>
                                    <td>&euro; ${rate.cost.toFixed(2)}</td>
                                </tr>
                            `)
                        });

                        $('#modal_shipping_method_results').html(rateElements.join(''));
                    }
                });
            }

            if (target === changeIdentifier) {
                const {address, postcode, city, country} = getShippingAddressDetails();

                $.ajax({
                    method: 'POST',
                    url: buz_wc_settings.ajax_url,
                    data: {
                        action: getActionByMode(modalMode),
                        security: buz_wc_settings.ajax_nonce,
                        destination: {
                            address,
                            postcode,
                            city,
                            country
                        }
                    }
                }).done((response) => {
                    const {success, data} = response;

                    if (success === false) {
                        alert(buz_wc_settings.i18n_unknown_error_occured);
                    } else {
                        rates = data;
                        const rateElements = [];

                        data.forEach((rate) => {
                            rateElements.push(`
                                <tr>
                                    <td>
                                        <label>
                                            <input type="radio" name="service_id" value="${rate.id}" data-costs="${rate.cost}" data-meta_data="${JSON.stringify(rate.meta_data)}" /> ${rate.label}
                                        </label>
                                    </td>
                                    <td>&euro; ${rate.cost.toFixed(2)}</td>
                                </tr>
                            `)
                        });

                        $('#modal_shipping_method_results').html(rateElements.join(''));
                    }
                });
            }

            if (target === getServicesIdentifier) {
                const form = $('#get_services_form');
                const footer = form.closest('.wc-backbone-modal-main').find('footer');

                footer.hide();

                let order = orderId;
                if(!order) {
                    order = buz_wc_settings.order_id
                }

                form.on('submit', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const formData = $(this).serializeArray().reduce(function(obj, item) {
                        obj[item.name] = item.value;
                        return obj;
                    }, {});

                    $.ajax({
                        method: 'POST',
                        url: buz_wc_settings.ajax_url,
                        data: {
                            action: 'boekuwzending_admin_get_services',
                            security: buz_wc_settings.ajax_nonce,
                            item: {
                                orderId: order,
                                ...formData
                            }
                        }
                    }).done((response) => {
                        const rates = response;
                        const rateElements = [];

                        if(rates.length > 0) {
                            rates.forEach((rate) => {
                                rateElements.push(`
                                <tr>
                                    <td>
                                        <label>
                                            <input type="radio" name="service_id" value="${rate.id}" data-costs="${rate.price}" /> ${rate.description}
                                        </label>
                                    </td>
                                    <td>&euro; ${rate.price.toFixed(2)}</td>
                                </tr>
                            `)
                            });

                            footer.show();
                        } else {
                            rateElements.push(`
                                <tr>
                                    <td colspan="2" style="text-align: left;">
                                        Geen verzendmogelijkheden gevonden voor de opgegeven dimensies  
                                    </td>
                                </tr>
                            `)
                        }

                        $('#modal_shipping_method_results').html(rateElements.join(''));
                    });
                })
            }
        })
        .on('wc_backbone_modal_response', (e, target, formData) => {
            if (target === addIdentifier) {
                let rateData = null;

                rates.forEach((rate) => {
                    if (formData.service_id === rate.id) {
                        rateData = rate;
                    }
                });

                if (rateData === null) {
                    alert(buz_wc_settings.i18n_no_shipping_rate_found);

                    return;
                }

                blockMetaBox();

                $.ajax({
                    method: 'POST',
                    url: buz_wc_settings.ajax_url,
                    data: {
                        action: getSaveActionByMode(modalMode),
                        security: buz_wc_settings.ajax_nonce,
                        orderId: buz_wc_settings.order_id,
                        rate: rateData
                    }
                }).done((response) => {
                    const {success} = response;

                    if (success === false) {
                        alert(buz_wc_settings.i18n_error_while_saving_rate);
                    } else {
                        reloadOrderItems();

                        renderMetabox();
                    }
                });
            }

            if (target === changeIdentifier) {
                let rateData = null;

                rates.forEach((rate) => {
                    if (formData.service_id === rate.id) {
                        rateData = rate;
                    }
                });

                if (rateData === null) {
                    alert(buz_wc_settings.i18n_no_shipping_rate_found);

                    return;
                }

                blockMetaBox();

                $.ajax({
                    method: 'POST',
                    url: buz_wc_settings.ajax_url,
                    data: {
                        action: getSaveActionByMode(modalMode),
                        security: buz_wc_settings.ajax_nonce,
                        orderId: buz_wc_settings.order_id,
                        orderItemId: currentOrderItemId,
                        rate: rateData
                    }
                }).done((response) => {
                    const {success} = response;

                    if (success === false) {
                        alert(buz_wc_settings.i18n_error_while_saving_rate);
                    } else {
                        reloadOrderItems();

                        renderMetabox();
                    }
                });
            }

            if (target === getServicesIdentifier) {
                let order = orderId;
                if(!order) {
                    order = buz_wc_settings.order_id
                }

                $.ajax({
                    method: 'POST',
                    url: buz_wc_settings.ajax_url,
                    data: {
                        action: 'boekuwzending_admin_create_shipment',
                        security: buz_wc_settings.ajax_nonce,
                        orderId: order,
                        data: formData
                    }
                }).done((response) => {
                    location.reload()
                });
            }
        });

    $( document ).ajaxSuccess(function( event, xhr, settings ) {
        const data = Object.fromEntries(new URLSearchParams(settings.data));

        if (data.action === 'woocommerce_add_order_item' || data.action === 'woocommerce_remove_order_item') {
            renderMetabox(true);
        }
    });

    $('#woocommerce-order-items')
        .on('click', '[data-action="change_shipping_method"]', (e) => {
            e.preventDefault();

            const item = $(e.currentTarget);

            modalMode = 'delivery';

            currentOrderItemId = $(item).attr('data-id');

            const requirementError = validateRequirements();

            if (requirementError) {
                alert(requirementError);

                return;
            }

            $(this).WCBackboneModal({
                template: changeIdentifier,
            });
        })
        .on('click', '[data-action="choose_pickup_point"]', (e) => {
            e.preventDefault();

            const item = $(e.currentTarget);
            const distributor = item.attr('data-distributor');

            modalMode = 'pickup';

            currentOrderItemId = $(item).attr('data-id');

            const requirementError = validateRequirements();

            if (requirementError) {
                alert(requirementError);

                return;
            }

            const {postcode, country} = getShippingAddressDetails();

            buzPickupPointLoader.show({
                postcode: postcode.replace(' ', ''),
                country,
                filter: {
                    distributor: [distributor]
                }
            });
        })
        .on('change', '.shipping_method', (e) => {
            const item = $(e.currentTarget);
            const line = item.closest('.shipping');
            const showDelivery = item.val() === 'buz_wc_shipping_method_delivery';
            const showPickupPoint = item.val() === 'buz_wc_shipping_method_pickuppoint';

            line.find('[data-action="choose_shipping_method"]').toggle(showDelivery);
            line.find('[data-action="choose_pickup_point"]').toggle(showPickupPoint);
            line.find('.shipping_method_name').val('Verzendmethoden');
        });

    renderMetabox(true);
});

function renderMetabox(enableBlock) {
    const metabox = $('#boekuwzending_order_metabox');

    metabox.off('click', '[data-action="add_shipping_method"]');
    metabox.off('click', '[data-action="get_shipping_services"]');
    metabox.off('click', '[data-action="create_shipping"]');
    metabox.off('click', '[data-action="create_additional_label"]');
    metabox.off('click', '[data-action="retrieve_status"]');
    metabox.off('click', '[data-action="choose_pickup_point"]');

    if (enableBlock) {
        blockMetaBox();
    }

    $.ajax({
        method: 'POST',
        dataType: 'html',
        url: buz_wc_settings.ajax_url,
        data: {
            action: 'boekuwzending_admin_metabox_order_render',
            security: buz_wc_settings.ajax_nonce,
            orderId: buz_wc_settings.order_id,
            isNewOrder: buz_wc_settings.is_new_order,
        }
    }).done((html) => {
        metabox.html(html);

        initializeMetaboxEvents();

        if (enableBlock) {
            unblockMetaBox();
        }
    });
}

function initializeMetaboxEvents() {
    const metabox = $('#boekuwzending_order_metabox');

    metabox
        .on('click', '[data-action="add_shipping_method"]', (e) => {
            e.preventDefault();

            modalMode = null;

            const requirementError = validateRequirements();

            if (requirementError) {
                alert(requirementError);

                return;
            }

            $('body').WCBackboneModal({
                template: addIdentifier,
            });
        })
        .on('click', '[data-action="get_shipping_services"]', (e) => getServicesModal(e))
        .on('click', '[data-action="create_shipping"]', (e) => {
            e.preventDefault();

            blockMetaBox();

            metabox.block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });

            $.ajax({
                method: 'POST',
                url: buz_wc_settings.ajax_url,
                data: {
                    action: 'boekuwzending_admin_create_shipment',
                    security: buz_wc_settings.ajax_nonce,
                    orderId: buz_wc_settings.order_id,
                }
            }).done((response) => {
                const { data } = response;

                if ('notesHtml' in data) {
                    refreshOrderNotes(data.notesHtml);
                }

                renderMetabox();
                reloadOrderItems();
            }).fail(processXhrErrors);
        })
        .on('click', '[data-action="create_additional_label"]', (e) => {
            e.preventDefault();

            blockMetaBox();

            $.ajax({
                method: 'POST',
                url: buz_wc_settings.ajax_url,
                data: {
                    action: 'boekuwzending_admin_create_additional_label',
                    security: buz_wc_settings.ajax_nonce,
                    orderId: buz_wc_settings.order_id,
                }
            }).done((response) => {
                const { data } = response;

                if ('notesHtml' in data) {
                    refreshOrderNotes(data.notesHtml);
                }

                renderMetabox();
                reloadOrderItems();
            }).fail(processXhrErrors);
        })
        .on('click', '[data-action="retrieve_status"]', (e) => {
            e.preventDefault();

            blockMetaBox();

            $.ajax({
                method: 'POST',
                url: buz_wc_settings.ajax_url,
                data: {
                    action: 'boekuwzending_admin_retrieve_status',
                    security: buz_wc_settings.ajax_nonce,
                    orderId: buz_wc_settings.order_id,
                }
            }).done(() => {
                renderMetabox();
            }).fail(processXhrErrors);
        })
        .on('click', '[data-action="choose_pickup_point"]', (e) => {
            e.preventDefault();

            const item = $(e.currentTarget);
            const distributor = item.attr('data-distributor');

            modalMode = 'pickup';

            const requirementError = validateRequirements();

            if (requirementError) {
                alert(requirementError);

                return;
            }

            const {postcode, country} = getShippingAddressDetails();

            buzPickupPointLoader.show({
                postcode: postcode.replace(' ', ''),
                country,
                filter: {
                    distributor: [distributor]
                }
            });
        })
    ;

    $('[data-buz-tooltip="true"]').tipTip({attribute: "title", fadeIn: 50, fadeOut: 50, delay: 200});
}

function getServicesModal(e) {
    e.preventDefault();

    const href = $(e.currentTarget).attr('href');

    const url = new URL(href);

    if (url.searchParams.has('order_id')) {
        orderId = url.searchParams.get('order_id');
    } else {
        orderId = null;
    }

    $('body').WCBackboneModal({
        template: getServicesIdentifier,
    });
}

function blockMetaBox() {
    $('#boekuwzending_order_metabox').block({
        message: null,
        overlayCSS: {
            background: '#fff',
            opacity: 0.6
        }
    });
}

function unblockMetaBox() {
    $('#boekuwzending_order_metabox').unblock();
}

function refreshOrderNotes(notesHtml) {
    $('.order_notes', '#woocommerce-order-notes').replaceWith(notesHtml);
}

function getShippingAddressDetails() {
    const address = $('#_shipping_address_1').val();
    const postcode = $('#_shipping_postcode').val();
    const city = $('#_shipping_city').val();
    const country = $('#_shipping_country').val();

    return {
        address,
        postcode,
        city,
        country
    };
}

function validateRequirements() {
    const {address, postcode, city, country} = getShippingAddressDetails();

    let error = null;

    if (!address && !postcode && !city && !country) {
        error = buz_wc_settings.i18n_no_shipping_information;
    }

    if (!postcode) {
        error = buz_wc_settings.i18n_no_shipping_postcode;
    } else if (!address) {
        error = buz_wc_settings.i18n_no_shipping_address;
    } else if (!city) {
        error = buz_wc_settings.i18n_no_shipping_city;
    } else if (!country) {
        error = buz_wc_settings.i18n_no_shipping_country;
    }

    return error;
}

function getActionByMode(modalMode) {
    if (modalMode === 'delivery') {
        return 'boekuwzending_get_delivery_rates';
    }

    if (modalMode === 'pickup') {
        return 'boekuwzending_get_pickup_rates';
    }

    return 'boekuwzending_get_rates';
}

function getSaveActionByMode(modalMode) {
    if (modalMode === 'delivery') {
        return 'boekuwzending_admin_save_delivery';
    }

    if (modalMode === 'pickup') {
        return 'boekuwzending_admin_save_pick_up_point';
    }

    return 'boekuwzending_admin_add_shipping_method';
}

function initializePickupPointLoader() {
    const url = new URL(buz_wc_settings.url);

    url.searchParams.append('token', buz_wc_settings.token);

    return new BuzPickupPointLoader({
        url,
        onSelect: (pickupPoint) => { // onSelect callback is required
            const {
                identifier,
                name,
                distributor,
                address
            } = pickupPoint;

            $.ajax({
                method: 'POST',
                url: buz_wc_settings.ajax_url,
                data: {
                    action: getSaveActionByMode('pickup'),
                    security: buz_wc_settings.ajax_nonce,
                    orderId: buz_wc_settings.order_id,
                    pick_up_point: {
                        identifier,
                        name,
                        distributor,
                        address
                    }
                }
            }).done((response) => {
                const {success} = response;

                if (success === false) {
                    alert(buz_wc_settings.i18n_error_while_saving_pick_up);
                } else {
                    reloadOrderItems();
                    renderMetabox(true);
                }
            }).fail(processXhrErrors);
        }
    });
}

function reloadOrderItems() {
    $('#woocommerce-order-items').trigger('wc_order_items_reload');
}

function processXhrErrors(response) {
    const responseText = JSON.parse(response.responseText);
    const { data } = responseText;

    if (data) {
        $(data).insertAfter('.wp-header-end');

        $(document).trigger('wp-updates-notice-added');
    }

    unblockMetaBox();
}