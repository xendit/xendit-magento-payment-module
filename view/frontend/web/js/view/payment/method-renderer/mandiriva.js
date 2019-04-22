define(
  [
      'Magento_Checkout/js/view/payment/default'
  ],
  function (Component) {
      'use strict';

      return Component.extend({
          defaults: {
              template: 'Xendit_M2Invoice/payment/mandiriva'
          },

          getCode: function() {
              return 'mandiriva';
          },

          getTest: function() {
              return '1';
          }
      });
  }
);