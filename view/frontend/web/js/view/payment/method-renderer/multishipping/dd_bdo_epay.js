define(
    [
        'Xendit_M2Invoice/js/view/payment/method-renderer/dd_bdo_epay'
    ],
    function (
        Component
        ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Xendit_M2Invoice/payment/multishipping/description',
                redirectAfterPlaceOrder: false,
            },

            getMethodImage: function () {
                return window.checkoutConfig.payment[this.item.method].image;
            }
        });
    }
);
