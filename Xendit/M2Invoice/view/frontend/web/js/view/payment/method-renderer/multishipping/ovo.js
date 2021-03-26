define(
    [
        'jquery',
        'Xendit_M2Invoice/js/view/payment/method-renderer/ovo',
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
                template: 'Xendit_M2Invoice/payment/multishipping/ovo',
                redirectAfterPlaceOrder: false,
            },

            placeOrder: function () {
                this.isPlaceOrderActionAllowed(false);
                var self = this;

                try {
                    var ovoPhoneNumber = $('#ovo_ovo_number').val();

                    if (!self.isPhoneNumber(ovoPhoneNumber)) {
                        alert('Invalid OVO phone number, please check again');
                        self.isPlaceOrderActionAllowed(true);
                        self.unblock();
                        return;
                    }
                    utility.setCookie('xendit_phone_number',ovoPhoneNumber,true);
                    utility.setCookie('xendit_payment_method','ovo',true);

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