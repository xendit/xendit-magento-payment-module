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
                template: 'Xendit_M2Invoice/payment/cc-hosted',
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
                return 'CC_SUBSCRIPTION'
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
                var uiUrl = window.checkoutConfig.payment.xendit.ui_url;
                var xenditScript = document.createElement('script');
                xenditScript.src = uiUrl + '/js/xendit-hp.min.js';
                document.body.appendChild(xenditScript);

                window.location.replace(url.build('xendit/checkout/redirect'));

                function renderHostedPayment(hpId, hpData) { // need?
                    var retry = 0;
                    var hpExecuted = false;

                    var hpTimer = setInterval(function () {
                        try {
                            HostedPayment.render(hpId, hpData);
                            hpExecuted = true;
                        } catch (e) {
                            retry++;

                            if (retry === 5) {
                                messageList.addErrorMessage({
                                    message: 'Please wait while Xendit is getting ready..'
                                });
                            }
                        } finally {
                            if (hpExecuted) {
                                $('.loading-mask').hide();
                                clearInterval(hpTimer);
                            }
                        }
                    }, 1000);
                }
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