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
                template: 'Xendit_M2Invoice/payment/linkaja',
                redirectAfterPlaceOrder: false
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
                return window.checkoutConfig.payment.linkaja.description;
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
                if ($("[class='xendit-overlay-box']").length === 0) {
                    var overlayDiv = $( "<div class='xendit-overlay-box'>" +
                        "<div id='xendit-overlay-content'>\n" +
                        "  <span class='xendit-overlay-text' style='margin-top: 80px;'>Periksa kembali telepon selular Anda, buka aplikasi Linkaja anda dan</span>\n" +
                        "  <span class='xendit-overlay-text'>konfirmasikan transaksi anda dengan memasukkan PIN</span>" +
                        "</div>" +
                        "</div>" );
                    $( 'body' ).append(overlayDiv);
                }

                $( "[class='xendit-overlay-box']" ).css("display", "flex");
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
                    var linkajaPhoneNumber = $('#linkaja_linkaja_number').val();

                    if (!self.isPhoneNumber(linkajaPhoneNumber)) {
                        alert('Invalid LinkAja phone number, please check again');
                        self.isPlaceOrderActionAllowed(true);
                        self.unblock();
                        return;
                    }

                    var paymentData = self.getData();
                    paymentData.additional_data = {
                        phone_number: linkajaPhoneNumber
                    };

                    var placeOrder = placeOrderAction(paymentData, false);

                    $.when(placeOrder)
                        .fail(function () {
                            self.isPlaceOrderActionAllowed(true);
                            self.unblock();
                        })
                        .done(function () {
                            self.afterPlaceOrder();
                        });
                    return false;
                } catch (e) {
                    alert(e);
                    self.isPlaceOrderActionAllowed(true);
                    self.unblock();
                }
            },

            isPhoneNumber: function (string) {
                var pattern = /^\d+$/;

                return pattern.test(string);
            },

            block: function() {
                var overlayBox
                if ($("[class='xendit-overlay-box']").length === 0) {
                    var overlayDiv = $( "<div class='xendit-overlay-box'>" +
                        "<div id='xendit-overlay-content'>\n" +
                        "  <span class='xendit-overlay-text' style='margin-top: 80px;'>Periksa kembali telepon selular Anda, buka aplikasi Linkaja anda dan</span>\n" +
                        "  <span class='xendit-overlay-text'>konfirmasikan transaksi anda dengan memasukkan PIN</span>" +
                        "</div>" +
                        "</div>" );
                    $( 'body' ).append(overlayDiv);
                }

                $( "[class='xendit-overlay-box']" ).css("display", "flex");
                // return;
            },

            unblock: function() {
                $('.xendit-overlay-box').css("display", "none");
            }
        });
    }
);