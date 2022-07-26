/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
    'utility',
    'jquery',
    'jquery-ui-modules/widget',
    'mage/translate'
], function (utility,$) {
    'use strict';
    return function (widget){
        $.widget('mage.orderOverview', widget, {

            /**
             * Verify that all agreements and terms/conditions are checked. Show the Ajax loader. Disable
             * the submit button (i.e. Place Order).
             * @return {Boolean}
             * @private
             */
            _showLoader: function () {
                if ($(this.options.agreements).find('input[type="checkbox"]:not(:checked)').length > 0) {
                    return false;
                }
                this.element.find(this.options.pleaseWaitLoader).show().end()
                    .find(this.options.placeOrderSubmit).prop('disabled', true).css('opacity', this.options.opacity);

                if(utility.getCookie('xendit_phone_number') && utility.getCookie('xendit_payment_method') == 'ovo'){
                    var overlayDiv = $( "<div class='xendit-overlay-box'>" +
                        "<div id='xendit-overlay-content'>\n" +
                        "  <span class='xendit-overlay-text' style='margin-top: 80px;'>Payment Request Sent to OVO, please check your OVO application</span>\n" +
                        "</div>" +
                        "</div>" );
                    $( 'body' ).append(overlayDiv);

                    $( "[class='xendit-overlay-box']" ).css("display", "flex");
                }

                return true;
            }
        });

        return $.mage.orderOverview;
    }

});
