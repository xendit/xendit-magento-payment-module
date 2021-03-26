var config = {
    map: {
        '*': {
            utility: 'Xendit_M2Invoice/js/utility/utility',
            checkQrcodeStatus: 'Xendit_M2Invoice/js/api/qrcode/check-status',
        }
    },
    config : {
        mixins : {
            'Magento_Multishipping/js/overview':{
                'Xendit_M2Invoice/js/multishipping/overview-mixin':true
            }
        }
    }
};
