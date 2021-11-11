var config = {
    map: {
        '*': {
            utility: 'Xendit_M2Invoice/js/utility/utility'
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
