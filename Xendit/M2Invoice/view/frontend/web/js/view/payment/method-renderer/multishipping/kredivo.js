define(
    [
        'jquery',
        'Xendit_M2Invoice/js/view/payment/method-renderer/kredivo',
        'utility'
    ],
    function (
        $,
        Component,
        utility
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Xendit_M2Invoice/payment/multishipping/kredivo',
                redirectAfterPlaceOrder: false,
            },

            placeOrder: function () {
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
                    // set the cookie value
                    utility.setCookie('xendit_payment_type',kredivoPaymentType,true);

                    $('#multishipping-billing-form').submit();

                    return true;
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