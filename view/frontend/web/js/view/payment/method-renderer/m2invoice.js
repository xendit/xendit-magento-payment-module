define(
    [
        'Magento_Checkout/js/view/payment/default'
    ],
    function (Component) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Xendit_M2Invoice/payment/m2invoice'
            },

            getCode: function() {
                return 'm2invoice';
            },

            getTest: function() {
                return '1';
            }
        });
    }
);