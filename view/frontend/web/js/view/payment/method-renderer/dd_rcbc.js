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
                return 'dd_rcbc';
            },

            getMethod: function() {
                return 'DD_RCBC';
            },

            getMethodImage: function () {
                return window.checkoutConfig.payment[this.item.method].image;
            },

            getTest: function() {
                return '1';
            },

            getDescription: function() {
                return window.checkoutConfig.payment.dd_rcbc.description;
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
                window.location.replace(url.build('xendit/checkout/invoice?preferred_method=DD_RCBC'));
            },

            validate: function() {
                var billingAddress = quote.billingAddress();

                self.messageContainer.clear();

                if (!billingAddress) {
                    self.messageContainer.addErrorMessage({'message': 'Please enter your billing address'});
                    return false;
                }


                return true;
            }
        });
    }
);
