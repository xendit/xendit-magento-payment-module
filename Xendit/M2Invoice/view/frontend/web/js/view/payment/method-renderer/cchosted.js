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
                var xenditScript = document.createElement('script');
                xenditScript.src = 'http://localhost:8080/js/hp.js';
                document.body.appendChild(xenditScript);

                $.ajax({
                    type: 'get',
                    url: url.build('xendit/checkout/redirect'),
                    success: function (data) {
                        var hpId = data.id;
                        var hpData = {
                            token: data.hp_token,
                            onSuccess: function () {
                                window.location.replace(url.build('xendit/checkout/processhosted'));
                            },
                            onError: function () {
                                window.location.replace(url.build('xendit/checkout/failure?order_id=' + data.order_id));
                            },
                            onClose: function () {
                                window.location.replace(url.build('xendit/checkout/failure?order_id=' + data.order_id));
                            }
                        };

                        renderHostedPayment(hpId, hpData);
                    }
                });

                function renderHostedPayment(hpId, hpData) {
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