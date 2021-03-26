define(
    [
        'Magento_Checkout/js/view/payment/default',
        'mage/url',
        'Magento_Checkout/js/model/quote'
    ],
    function (
        Component,
        url,
        quote,
        ) {
        'use strict';

        var self;

        return Component.extend({
            defaults: {
                template: 'Xendit_M2Invoice/payment/invoiceva',
                redirectAfterPlaceOrder: false
            },

            initialize: function() {
                this._super();
                self = this;
            },

            getCode: function() {
                return 'alfamart';
            },

            getMethod: function() {
                return 'Alfamart'
            },

            getTest: function() {
                return '1';
            },

            getDescription: function() {
                return window.checkoutConfig.payment.alfamart.description;
            },

            getTestDescription: function () {
                var environment = window.checkoutConfig.payment.xendit.xendit_env;

                if (environment !== 'test') {
                    return {};
                }

                return {
                    prefix: window.checkoutConfig.payment.xendit.test_prefix,
                    content: window.checkoutConfig.payment.xendit.test_content
                };
            },

            afterPlaceOrder: function () {
                window.location.replace(url.build('xendit/checkout/invoice?preferred_method=Alfamart'));
            },

            validate: function() {
                var billingAddress = quote.billingAddress();
                var totals = quote.totals();

                self.messageContainer.clear();

                if (!billingAddress) {
                    self.messageContainer.addErrorMessage({'message': 'Please enter your billing address'});
                    return false;
                }

                if (totals.grand_total < window.checkoutConfig.payment.alfamart.min_order_amount) {
                    self.messageContainer.addErrorMessage({'message': 'Xendit doesn\'t support purchases less than Rp 10,000.'});
                    return false;
                }

                return true;
            }
        });
    }
);