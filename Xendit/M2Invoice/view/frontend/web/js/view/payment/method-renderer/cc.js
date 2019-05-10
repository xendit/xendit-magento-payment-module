define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Payment/js/model/credit-card-validation/credit-card-data',
        'Magento_Payment/js/model/credit-card-validation/credit-card-number-validator',
        'Magento_Checkout/js/action/place-order',
        'mage/url',
    ],
    function (
        $,
        Component,
        creditCardData,
        cardNumberValidator,
        placeOrderAction,
        url,
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Xendit_M2Invoice/payment/cc',
                creditCardType: '',
                creditCardExpYear: '',
                creditCardExpMonth: '',
                creditCardNumber: '',
                creditCardSsStartMonth: '',
                creditCardSsStartYear: '',
                creditCardVerificationNumber: '',
                selectedCardType: null
            },

            initObservable: function() {
                this._super()
                    .observe([
                        'creditCardType',
                        'creditCardExpYear',
                        'creditCardExpMonth',
                        'creditCardNumber',
                        'creditCardVerificationNumber',
                        'creditCardSsStartMonth',
                        'creditCardSsStartYear',
                        'selectedCardType'
                    ]);
                return this;
            },

            initialize: function() {
                var self = this;
                this._super();

                this.creditCardNumber.subscribe(function (value) {
                    var result;
                    self.selectedCardType(null);

                    if (value === '' || value === null) {
                        return false;
                    }

                    result = cardNumberValidator(value);

                    if (!result.isPotentiallyValid && !result.isValid) {
                        return false;
                    }

                    if (result.card !== null) {
                        self.selectedCardType(result.card.type);
                        creditCardData.creditCard = result.card;
                    }

                    if (result.isValid) {
                        creditCardData.creditCardNumber = value;
                        self.creditCardType(result.card.type);
                    }
                });

                this.creditCardExpYear.subscribe(function(value) {
                    creditCardData.expirationYear = value;
                });
 
                this.creditCardExpMonth.subscribe(function(value) {
                    creditCardData.expirationMonth = value;
                });
 
                this.creditCardVerificationNumber.subscribe(function(value) {
                    creditCardData.cvvCode = value;
                });

                var xenditJsUrl = 'https://js.xendit.co/v1/xendit.min.js';
                var scriptTag = document.createElement('script');
                scriptTag.src = xenditJsUrl;
                document.body.appendChild(scriptTag);
            },

            context: function() {
                return this;
            },

            getCode: function() {
                return 'cc';
            },

            getTest: function() {
                return '1';
            },

            isActive: function() {
                return true;
            },

            getCcAvailableTypesValues: function() {
                return _.map(this.getCcAvailableTypes(), function(value, key) {
                    return {
                        'value': key,
                        'type': value
                    }
                });
            },

            getCcMonthsValues: function() {
                return _.map(this.getCcMonths(), function(value, key) {
                    return {
                        'value': key,
                        'month': value
                    }
                });
            },

            getCcYearsValues: function() {
                return _.map(this.getCcYears(), function(value, key) {
                    return {
                        'value': key,
                        'year': value
                    }
                });
            },

            getCcAvailableTypes: function() {
                return window.checkoutConfig.payment.m2invoice.availableTypes['cc'];
            },

            getCcMonths: function() {
                return window.checkoutConfig.payment.m2invoice.months['cc'];
            },
 
            getCcYears: function() {
                return window.checkoutConfig.payment.m2invoice.years['cc'];
            },
 
            hasVerification: function() {
                return window.checkoutConfig.payment.m2invoice.hasVerification;
            },

            getDescription: function() {
                return `Bayar pesanan dengan kartu kredit atau debit anda melalui Xendit`;
            },

            getTestDescription: function () {
                var environment = window.checkoutConfig.payment.m2invoice.xendit_env;

                if (environment !== 'test') {
                    return {};
                }

                return {
                    prefix: window.checkoutConfig.payment.m2invoice.test_prefix,
                    content: window.checkoutConfig.payment.m2invoice.test_content
                };
            },

            mapMonthValue: function (val) {
                if (!val) return;

                if (val.length !== 2) {
                    return '0' + val;
                }

                return val;
            },

            placeOrder: function (data, event) {
                var self = this;
                var publicKey = window.checkoutConfig.payment.m2invoice.public_api_key;

                Xendit.setPublishableKey(publicKey);
                
                var tokenData = {
                    card_number: creditCardData.creditCardNumber,
                    card_exp_month: this.mapMonthValue(creditCardData.expirationMonth),
                    card_exp_year: creditCardData.expirationYear,
                    is_multiple_use: true
                };

                if (!tokenData.card_number || !tokenData.card_exp_month || !tokenData.card_exp_year) {
                    alert('Please fill out the information needed before proceeding');
                    return;
                }

                Xendit.card.createToken(tokenData, function (err, token) {
                    if (err) {
                        // ?
                        return;
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

                    var placeOrder = placeOrderAction(paymentData, false);

                    $.when(placeOrder)
                        .fail(function () {
                        })
                        .done(function () {
                            self.afterPlaceOrder();
                        });

                    return false;
                });
            },

            afterPlaceOrder: function () {
                window.location.replace(url.build('xendit/checkout/redirect'));
            },
        });
    }
);