define(
    [
        'Magento_Checkout/js/view/payment/default',
        'mage/url',
        'Magento_Checkout/js/model/quote',
        'underscore',
        'jquery',
        'Magento_Checkout/js/action/place-order',
        'Magento_Ui/js/model/messageList'
    ],
    function (
        Component,
        url,
        quote,
        _,
        $,
        placeOrderAction,
        messageList
    ) {
        'use strict';

        var self;

        return Component.extend({
            defaults: {
                template: 'Xendit_M2Invoice/payment/cc-hosted',
                redirectAfterPlaceOrder: false
            },

            initialize: function() {
                this._super();
                self = this;
            },

            getCode: function() {
                return 'cchosted';
            },

            getMethod: function() {
                return 'CCHOSTED'
            },

            getTest: function() {
                return '1';
            },

            getDescription: function() {
                return 'Bayar pesanan dengan kartu kredit atau debit anda melalui Xendit';
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

            isActive: function() {
                return true;
            },

            afterPlaceOrder: function () {
                $.ajax({
                    type: 'get',
                    url: url.build('xendit/checkout/redirect'),
                    success: function (data) {
                        console.log(data);

                        window.location.replace(url.build('xendit/checkout/failure?order_id=' + data.order_id));
                    }
                })
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
            },
        });
    }
);