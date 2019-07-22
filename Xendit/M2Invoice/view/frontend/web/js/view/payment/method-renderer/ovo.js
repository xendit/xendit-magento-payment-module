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
                template: 'Xendit_M2Invoice/payment/ovo',
                redirectAfterPlaceOrder: false
            },

            initialize: function() {
                this._super();
                self = this;
            },

            getCode: function() {
                return 'ovo';
            },

            getMethod: function() {
                return 'OVO'
            },

            getTest: function() {
                return '1';
            },

            getDescription: function() {
                return 'Bayar pesanan dengan akun OVO anda melalui Xendit';
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

                if (totals.grand_total < 10000) {
                    self.messageContainer.addErrorMessage({'message': 'Xendit doesn\'t support purchases less than Rp 11.000.'});
                    return false;
                }

                return true;
            },

            placeOrder: function (data, event) {
                this.isPlaceOrderActionAllowed(false);
                var self = this;

                try {
                    var ovoPhoneNumber = $('#ovo_ovo_number').val();

                    if (!self.isPhoneNumber(ovoPhoneNumber)) {
                        alert('Invalid OVO phone number, please check again');
                        this.isPlaceOrderActionAllowed(true);
                        return;
                    }

                    console.log(ovoPhoneNumber);

                    var paymentData = self.getData();
                    paymentData.additional_data = {
                        phone_number: ovoPhoneNumber
                    };

                    var placeOrder = placeOrderAction(paymentData, false);

                    $.when(placeOrder)
                        .fail(function () {
                            self.isPlaceOrderActionAllowed(true);
                        })
                        .done(function () {
                            self.afterPlaceOrder();
                        });

                    return false;
                } catch (e) {
                    alert(e);
                    this.isPlaceOrderActionAllowed(true);
                }
            },

            isPhoneNumber: function (string) {
                var pattern = /^\d+$/;

                return pattern.test(string);
            }
        });
    }
);