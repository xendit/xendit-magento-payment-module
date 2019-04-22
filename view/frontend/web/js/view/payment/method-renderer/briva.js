define(
    [
        'Magento_Checkout/js/view/payment/default'
    ],
    function (Component) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Xendit_M2Invoice/payment/briva'
            },

            getCode: function() {
                return 'briva';
            },

            getTest: function() {
                return '1';
            }
        });
    }
);