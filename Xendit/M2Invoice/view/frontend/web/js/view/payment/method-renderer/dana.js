define(
    [
        'Magento_Checkout/js/view/payment/default',
        'mage/url',
        'Magento_Checkout/js/model/quote',
        'underscore',
        'jquery',
    ],
    function (
        Component,
        url,
        quote,
        _,
        $
    ) {
        'use strict';

        var self;

        return Component.extend({
            defaults: {
                template: 'Xendit_M2Invoice/payment/dana',
                redirectAfterPlaceOrder: false
            },

            initialize: function() {
                this._super();
                self = this;
            },

            getCode: function() {
                return 'dana';
            },

            getMethod: function() {
                return 'DANA'
            },

            getTest: function() {
                return '1';
            },

            getDescription: function() {
                return window.checkoutConfig.payment.dana.description;
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
                        "<div id='xendit-overlay-content'></div>" +
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

                if (totals.grand_total < window.checkoutConfig.payment.dana.min_order_amount) {
                    self.messageContainer.addErrorMessage({'message': 'Xendit doesn\'t support purchases less than Rp 1.'});
                    return false;
                }

                return true;
            },
        });
    }
);
