define(
    [
        'Xendit_M2Invoice/js/view/payment/method-renderer/cchosted'
    ],
    function (
        Component
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Xendit_M2Invoice/payment/cc-hosted',
                redirectAfterPlaceOrder: false
            },

            getCode: function() {
                return 'cc_subscription';
            },

            getMethod: function() {
                return 'CC_SUBSCRIPTION';
            },

            getDescription: function() {
                return window.checkoutConfig.payment.m2invoice.card_subscription_description;
            }
        });
    }
); 