define([
    'jquery',
    'jquery/ui',
    'mage/mage'
], function ($) {
    'use strict';

    $.widget('qrcode.status', {
        options: {
            externalId: '',
            amount: 0,
            isMultishipping: false,
            checkStatusUrl: '',
            redirectUrl: ''
        },
        /** @inheritdoc */
        _create: function () {
            let self = this;
            self.checkStatus();
        },

        /**
         * AJAX request for check qrcode status
         * @param form
         */
        checkStatus: function () {
            let self = this;
            let dataBlock = $('.qrcode-data');
            let statusUrl = (self.options && self.options.checkStatusUrl != "undefined")? self.options.checkStatusUrl : dataBlock.attr('data-check-status-url');
            let redirectUrl = (self.options && self.options.redirectUrl != "undefined")? self.options.redirectUrl : dataBlock.attr('data-redirect-url');
            let externalId = (self.options && self.options.externalId != "undefined")? self.options.externalId : dataBlock.attr('data-external-id');
            let amount = (self.options && self.options.amount != "undefined")? self.options.amount : dataBlock.attr('data-amount');
            let isMultishipping = (self.options && self.options.isMultishipping != "undefined")? self.options.isMultishipping : dataBlock.attr('data-is-multishipping');
            $.ajax({
                url: statusUrl,
                data: {
                    "externalId": externalId
                },
                type: 'GET',
                dataType: 'json',
                showLoader: false,
                success: function (response) {
                    //var data = JSON.parse(response);
                    if (response && typeof response != "undefined") {
                        console.log("check status : ");
                        console.log(response.status);
                        if (response.status != "undefined" && response.status == "ACTIVE") {
                            // still not paid, re-check status after wait
                            setInterval(self.checkStatus,5000);
                        } else if (response.status != "undefined" && response.status == "INACTIVE")  {
                            console.log("check status : ");
                            console.log(response.status);
                            clearTimeout(self.checkStatus);
                            // already paid -> redirect to process order
                            self.redirectPost(redirectUrl, {
                                externalId: externalId,
                                amount: amount,
                                isMultishipping: isMultishipping
                            });
                        }
                    }
                }
            });
        },
        redirectPost: function(location, args)
        {
            var formArgs = '';
            $.each( args, function( key, value ) {
                formArgs += '<input type="hidden" name="'+key+'" value="'+value+'">';
            });
            let $form = $('<form id="submit-form" action="'+location+'" method="post">'+formArgs+'</form>');
            $('body').append($form);
            $form.submit();
        }
    });
    return $.qrcode.status;
});
