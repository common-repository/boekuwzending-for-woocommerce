import './../scss/checkout.scss';
const $ = window.jQuery;

window.addEventListener('DOMContentLoaded', () => {
    const url = new URL(buz_wc_settings.url);

    url.searchParams.append('token', buz_wc_settings.token);

    const buzPickupPointLoader = new BuzPickupPointLoader({
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
                    action: 'boekuwzending_save_pick_up_point',
                    security: buz_wc_settings.ajax_nonce,
                    pick_up_point: {
                        identifier,
                        name,
                        distributor,
                        address
                    }
                }
            }).done(() => {
                $('body').trigger('update_checkout');
            });
        }
    });

    const body = $('body');

    body.on('updated_checkout', (e, data) => {
        const methods = document.querySelectorAll('input.shipping_method');

        if (data.result !== 'success') {
            return;
        }

        const pickUpPointTrigger = document.querySelector('a[data-action="buz_choose_pick_up_point"]');

        if (pickUpPointTrigger) {
            pickUpPointTrigger.addEventListener('click', (e) => {
                e.preventDefault();

                const {postcode, country, distributor} = e.target.dataset;

                if (!postcode || !country) {
                    throw Error('Cannot load Pick up Points, missing "postcode" and/or "country"');
                }

                buzPickupPointLoader.show({
                    postcode,
                    country,
                    filter: {
                        distributor: [distributor]
                    }
                });
            })
        }
    })
});


