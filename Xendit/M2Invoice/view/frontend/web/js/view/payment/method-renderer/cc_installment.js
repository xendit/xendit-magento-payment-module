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
                return 'cc_installment';
            },

            getMethod: function() {
                return 'CC_INSTALLMENT'
            },

            getDescription: function() {
                return 'Bayar pesanan dengan cicilan kartu kredit anda melalui Xendit';
            }
        });
    }
);