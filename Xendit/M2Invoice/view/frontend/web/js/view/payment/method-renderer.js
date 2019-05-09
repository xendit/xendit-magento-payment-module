	
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'bniva',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/bniva'
            },
            {
                type: 'briva',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/briva'
            },
            {
                type: 'mandiriva',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/mandiriva'
            },
            {
                type: 'cc',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/cc'
            },
        );
        return Component.extend({});
    }
);