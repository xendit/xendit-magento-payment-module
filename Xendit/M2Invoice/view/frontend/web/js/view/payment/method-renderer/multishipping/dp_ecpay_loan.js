define(
    [
        'Xendit_M2Invoice/js/view/payment/method-renderer/dp_ecpay_loan'
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
                return window.checkoutConfig.payment.dp_ecpay_loan.image;
            }
        });
    }
);
