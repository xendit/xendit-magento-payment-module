define(
    [
        'jquery',
        'Xendit_M2Invoice/js/view/payment/method-renderer/linkaja',
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
                template: 'Xendit_M2Invoice/payment/multishipping/linkaja',
                redirectAfterPlaceOrder: false,
            },

            placeOrder: function () {
                this.isPlaceOrderActionAllowed(false);
                var self = this;

                try {
                    var linkajaPhoneNumber = $('#linkaja_linkaja_number').val();

                    if (!self.isPhoneNumber(linkajaPhoneNumber)) {
                        alert('Invalid LINKAJA phone number, please check again');
                        self.isPlaceOrderActionAllowed(true);
                        self.unblock();
                        return;
                    }
                    utility.setCookie('xendit_phone_number',linkajaPhoneNumber,true);

                    $('#multishipping-billing-form').submit();

                    return true;

                } catch (e) {
                    alert(e);
                    self.isPlaceOrderActionAllowed(true);
                    self.unblock();
                }
            },

        });
    }
);