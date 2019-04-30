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

        var self = this;

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
                return 'mandiriva';
            },

            getMethod: function() {
                return 'MANDIRI'
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

                if (!billingAddress.firstname || 
                    !billingAddress.lastname ||
                    !billingAddress.street ||
                    !billingAddress.city ||
                    !billingAddress.postcode ||
                    billingAddress.firstname.length == 0 ||
                    billingAddress.lastname.length == 0 ||
                    billingAddress.street.length == 0 ||
                    billingAddress.city.length == 0 ||
                    billingAddress.postcode.length == 0) {
                    self.messageContainer.addErrorMessage({'message': 'Please enter your billing address details'});
                    return false;
                }

                if (totals.grand_total < 11000) {
                    self.messageContainer.addErrorMessage({'message': 'Xendit doesn\'t support purchases less than Rp 11.000.'});
                    return false;
                }

                return true;
            }
        });
    }
);