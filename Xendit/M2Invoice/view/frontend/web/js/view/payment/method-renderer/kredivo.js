define(
    [
        'Magento_Checkout/js/view/payment/default',
        'mage/url',
        'Magento_Checkout/js/model/quote',
        'underscore',
        'jquery',
        'Magento_Checkout/js/action/place-order'
    ],
    function (
        Component,
        url,
        quote,
        _,
        $,
        placeOrderAction
        ) {
        'use strict';

        var self;

        return Component.extend({
            defaults: {
                template: 'Xendit_M2Invoice/payment/kredivo',
                redirectAfterPlaceOrder: false
            },

            initialize: function() {
                this._super();
                self = this;
            },

            getCode: function() {
                return 'kredivo';
            },

            getMethod: function() {
                return 'KREDIVO';
            },

            getTest: function() {
                return '1';
            },

            getDescription: function() {
                return window.checkoutConfig.payment.kredivo.description;
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
                window.location.replace(url.build('xendit/checkout/redirect'));
            },

            validate: function() {
                var billingAddress = quote.billingAddress();
                var totals = quote.totals();

                self.messageContainer.clear();

                if (!billingAddress) {
                    self.messageContainer.addErrorMessage({'message': 'Please enter your billing address'});
                    return false;
                }


                return true;
            },

            placeOrder: function (data, event) {
                this.isPlaceOrderActionAllowed(false);
                var self = this;

                try {
                    let errorMessageBlock = $(".messages .message-error div");
                    let messageBlock = $(".messages");
                    // hide error message block
                    errorMessageBlock.html("");
                    messageBlock.hide();

                    var kredivoPaymentType = $('#kredivo_payment_type').val();

                    if (!kredivoPaymentType || kredivoPaymentType === "") {
                        errorMessageBlock.html("Please select a payment type first.");
                        messageBlock.show();
                        self.isPlaceOrderActionAllowed(true); // enable "pay now" button
                        return false;
                    }
                    var paymentData = self.getData();
                    paymentData.additional_data = {
                        xendit_payment_type: kredivoPaymentType
                    };

                    var placeOrder = placeOrderAction(paymentData, false);

                    $.when(placeOrder)
                        .fail(function () {
                            self.isPlaceOrderActionAllowed(true);
                            //self.unblock();
                        })
                        .done(function () {
                            self.afterPlaceOrder();
                        });
                    return false;
                } catch (e) {
                    alert(e);
                    self.isPlaceOrderActionAllowed(true);
                }
            },
            /**
             * Get list of available Payment Types values
             * @returns {Object}
             */
            getPaymentTypeValues: function () {
                return _.map(this.getPaymentTypes(), function (key, value) {
                    return {
                        'value': value,
                        'text': key
                    };
                });
            },
            /**
             * Get list of available Payment Types
             * @returns {Object}
             */
            getPaymentTypes: function () {
                return {
                    '30_days':'30 days',
                    '3_months':'3 months',
                    '6_months':'6 months',
                    '12_months':'12 months'
                };
            }
        });
    }
);