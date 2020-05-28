define(
    [
        'jquery',
        'Xendit_M2Invoice/js/view/payment/method-renderer/cc',
        'Magento_Payment/js/model/credit-card-validation/credit-card-data',
        'Magento_Checkout/js/action/set-payment-information-extended',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/full-screen-loader'
    ],
    function (
        $,
        Component,
        creditCardData,
        setPaymentInformationExtended,
        additionalValidators,
        fullScreenLoader
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Xendit_M2Invoice/payment/multishipping/cc',
                creditCardType: '',
                creditCardExpYear: '',
                creditCardExpMonth: '',
                creditCardNumber: '',
                creditCardSsStartMonth: '',
                creditCardSsStartYear: '',
                creditCardVerificationNumber: '',
                selectedCardType: null
            },
            /**
             * @override
             */
            placeOrder: function () {
                try {
                    var self = this;
                    var publicKey = window.checkoutConfig.payment.m2invoice.public_api_key;
                    Xendit.setPublishableKey(publicKey);

                    var tokenData = {
                        card_number: creditCardData.creditCardNumber,
                        card_exp_month: this.mapMonthValue(creditCardData.expirationMonth),
                        card_exp_year: creditCardData.expirationYear,
                        is_multiple_use: true
                    };

                    Xendit.card.createToken(tokenData, function (err, token) {
                        if (err) {
                            return self.fail();
                        }

                        var paymentData = self.getData();
                        paymentData.additional_data = {
                            token_id: token.id,
                            masked_card_number: token.masked_card_number,
                            cc_type: $('#cc_cc_type').val(),
                            cc_number: $('#cc_cc_number').val(),
                            cc_exp_month: $('#cc_expiration').val(),
                            cc_exp_year: $('#cc_expiration_yr').val(),
                            cc_cid: $('#cc_cc_cid').val()
                        };

                        return self.setPaymentInformation(paymentData);
                    });
                } catch (e) {
                    return self.fail();
                }
            },

            /**
             * @override
             */
            setPaymentInformation: function (paymentData) {
                if (additionalValidators.validate()) {
                    fullScreenLoader.startLoader();
                    $.when(
                        setPaymentInformationExtended(
                            this.messageContainer,
                            paymentData,
                            true
                        )
                    ).done(this.done.bind(this))
                        .fail(this.fail.bind(this));
                }
            },

            /**
             * {Function}
             */
            fail: function () {
                fullScreenLoader.stopLoader();

                return this;
            },

            /**
             * {Function}
             */
            done: function () {
                fullScreenLoader.stopLoader();
                $('#multishipping-billing-form').submit();

                return this;
            }
        });
    }
);