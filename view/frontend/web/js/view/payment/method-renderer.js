
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
                type: 'bcava',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/bcava'
            },
            {
                type: 'alfamart',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/alfamart'
            },
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
                type: 'permatava',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/permatava'
            },
            {
                type: 'ovo',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/ovo'
            },
            {
                type: 'shopeepay',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/shopeepay'
            },
            {
                type: 'shopeepayph',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/shopeepayph'
            },
            {
                type: 'dana',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/dana'
            },
            {
                type: 'linkaja',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/linkaja'
            },
            {
                type: 'indomaret',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/indomaret'
            },
            {
                type: 'cc',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/cc'
            },
            {
                type: 'qris',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/qris'
            },
            {
                type: 'dd_bri',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/dd_bri'
            },
            {
                type: 'kredivo',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/kredivo'
            },
            {
                type: 'gcash',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/gcash'
            },
            {
                type: 'grabpay',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/grabpay'
            },
            {
                type: 'paymaya',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/paymaya'
            },
            {
                type: 'dd_bpi',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/dd_bpi'
            },
            {
                type: 'seven_eleven',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/seven_eleven'
            },
            {
                type: 'dd_ubp',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/dd_ubp'
            },
            {
                type: 'billease',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/billease'
            },
            {
                type: 'cebuana',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/cebuana'
            },
            {
                type: 'dp_palawan',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/dp_palawan'
            },
            {
                type: 'dp_mlhuillier',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/dp_mlhuillier'
            },
            {
                type: 'dp_ecpay_loan',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/dp_ecpay_loan'
            },
            {
                type: 'bjbva',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/bjbva'
            },
            {
                type: 'bsiva',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/bsiva'
            },
            {
                type: 'bssva',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/bssva'
            },
            {
                type: 'cashalo',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/cashalo'
            },
            {
                type: 'uangme',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/uangme'
            },
            {
                type: 'astrapay',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/astrapay'
            },
            {
                type: 'akulaku',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/akulaku'
            },
            {
                type: 'dd_rcbc',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/dd_rcbc'
            },
            {
                type: 'lbc',
                component: 'Xendit_M2Invoice/js/view/payment/method-renderer/lbc'
            },
        );
        return Component.extend({});
    }
);
