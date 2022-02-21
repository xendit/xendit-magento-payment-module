define(
    [
        'Magento_Checkout/js/view/payment/default',
        'mage/url',
        'Magento_Checkout/js/model/quote',
        'underscore',
        'jquery',
        'Magento_Ui/js/model/messageList'
    ],
    function (
        Component,
        url,
        quote,
        _,
        $,
        messageList
    ) {
        'use strict';

        var self;

        return Component.extend({
            defaults: {
                template: 'Xendit_M2Invoice/payment/cc-subscription',
                redirectAfterPlaceOrder: false
            },

            initialize: function() {
                this._super();
                self = this;
            },

            getCode: function() {
                return 'cc_subscription';
            },

            getMethod: function() {
                return 'CC_SUBSCRIPTION';
            },

            getMethodImage: function () {
                return window.checkoutConfig.payment[this.item.method].image;
            },

            getTest: function() {
                return '1';
            },

            getDescription: function() {
                return window.checkoutConfig.payment.cc_subscription.description;
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

            isActive: function() {
                return true;
            },

            afterPlaceOrder: function () {
                window.location.replace(url.build('xendit/checkout/redirect'));
            },

            validate: function() {
                var billingAddress = quote.billingAddress();

                self.messageContainer.clear();

                if (!billingAddress) {
                    self.messageContainer.addErrorMessage({'message': 'Please enter your billing address'});
                    return false;
                }

                return true;
            },
        });
    }
);
