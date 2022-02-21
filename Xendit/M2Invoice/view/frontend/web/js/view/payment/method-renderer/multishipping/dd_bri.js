define(
    [
        'Xendit_M2Invoice/js/view/payment/method-renderer/dd_bri'
    ],
    function (
        Component
        ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Xendit_M2Invoice/payment/multishipping/description',
                redirectAfterPlaceOrder: false
            },

            getMethodImage: function () {
                return window.checkoutConfig.payment.dd_bri.image;
            }
        });
    }
);
