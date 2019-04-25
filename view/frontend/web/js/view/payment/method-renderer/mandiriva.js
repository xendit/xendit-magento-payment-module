define(
    [
        'Magento_Checkout/js/view/payment/default'
    ],
    function (Component) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Xendit_M2Invoice/payment/invoiceva'
            },

            getCode: function() {
                return 'mandiriva';
            },

            getTest: function() {
                return '1';
            },

            getDescription: function() {
                return 'Bayar pesanan dengan transfer bank Mandiri dengan virtual account melalui Xendit';
            },

            getTestDescription: function () {
                var environment = window.checkoutConfig.payment.m2invoice.xendit_env;

                if (environment !== 'test') {
                    return;
                }

                return {
                    prefix: window.checkoutConfig.payment.m2invoice.test_prefix,
                    content: window.checkoutConfig.payment.m2invoice.test_content
                };
            },

            afterPlaceOrder: function () {
                window.location.replace(url.build('xendit/checkout/invoice'));
            },
        });
    }
);