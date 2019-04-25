define(
  [
      'Magento_Checkout/js/view/payment/default'
  ],
  function (Component) {
      'use strict';

      return Component.extend({
          defaults: {
              template: 'Xendit_M2Invoice/payment/cc'
          },

          getCode: function() {
              return 'cc';
          },

          getTest: function() {
              return '1';
          }
      });
  }
);