define(
    [
        'Magento_Checkout/js/view/payment/default',
        'mage/url',
        'Magento_Checkout/js/model/quote'
    ],
    function (
        Component,
        url,
        quote
        ) {
        'use strict';

        var self;

        return Component.extend({
            defaults: {
                template: 'Xendit_M2Invoice/payment/multishipping/description',
                redirectAfterPlaceOrder: false,
            },

            initialize: function() {
                this._super();
                self = this;
            },

            getCode: function() {
                return 'permatava';
            },

            getMethod: function() {
                return 'Permata'
            },

            getTest: function() {
                return '1';
            },

            getDescription: function() {
                return `Bayar pesanan dengan transfer bank ${self.getMethod()} dengan virtual account melalui Xendit`;
            },

            getTestDescription: function () {
                var environment = window.checkoutConfig.payment.m2invoice.xendit_env;

                if (environment !== 'test') {
                    return {};
                }

                return {
                    prefix: window.checkoutConfig.payment.m2invoice.test_prefix,
                    content: window.checkoutConfig.payment.m2invoice.test_content
                };
            },

            afterPlaceOrder: function () {
                window.location.replace(url.build('xendit/checkout/invoice?preferred_method=Permata'));
            },

            validate: function() {
                var billingAddress = quote.billingAddress();
                var totals = quote.totals();

                self.messageContainer.clear();

                if (!billingAddress) {
                    self.messageContainer.addErrorMessage({'message': 'Please enter your billing address'});
                    return false;
                }

                if (totals.grand_total < 10000) {
                    self.messageContainer.addErrorMessage({'message': 'Xendit doesn\'t support purchases less than Rp 11.000.'});
                    return false;
                }

                return true;
            }
        });
    }
);