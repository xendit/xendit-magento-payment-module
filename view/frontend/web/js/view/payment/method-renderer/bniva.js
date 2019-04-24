define(
    [
        'Magento_Checkout/js/view/payment/default'
    ],
    function (Component) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Xendit_M2Invoice/payment/bniva'
            },

            getCode: function() {
                return 'bniva';
            },

            getTest: function() {
                return '1';
            },

            getDescription: function() {
                return 'Bayar pesanan dengan transfer bank BNI dengan virtual account melalui Xendit';
            }
        });
    }
);