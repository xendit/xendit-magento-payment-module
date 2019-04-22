	
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
                type: 'm2invoice',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/m2invoice'
            }
        );
        return Component.extend({});
    }
);