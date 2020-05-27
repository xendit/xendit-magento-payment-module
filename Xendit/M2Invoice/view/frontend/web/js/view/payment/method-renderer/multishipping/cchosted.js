define(
    [
        'Xendit_M2Invoice/js/view/payment/method-renderer/cchosted',
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
                template: 'Xendit_M2Invoice/payment/multishipping/description',
                redirectAfterPlaceOrder: false
            },

            initialize: function() {
                this._super();
                self = this;
            }
        });
    }
);