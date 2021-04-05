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
                redirectAfterPlaceOrder: false,
            },

            initialize: function() {
                this._super();
                self = this;
            },

            getCode: function() {
                return 'linkaja';
            },

            getMethod: function() {
                return 'LINKAJA'
            },

            getTest: function() {
                return '1';
            },

            getDescription: function() {
                return window.checkoutConfig.payment.linkaja.description;;
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
                window.location.replace(url.build(`xendit/checkout/invoice?preferred_method=${self.getMethod()}`));
            },

            validate: function() {
                var billingAddress = quote.billingAddress();
                var totals = quote.totals();

                self.messageContainer.clear();

                if (!billingAddress) {
                    self.messageContainer.addErrorMessage({'message': 'Please enter your billing address'});
                    return false;
                }

                if (totals.grand_total < window.checkoutConfig.payment.linkaja.min_order_amount) {
                    self.messageContainer.addErrorMessage({'message': 'The minimum amount for using this payment is IDR 10,000. Please put more item(s) to reach the minimum amount. Code: 100001'});
                    return false;
                }

                return true;
            }
        });
    }
);