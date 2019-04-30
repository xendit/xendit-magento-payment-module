define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Payment/js/model/credit-card-validation/credit-card-data',
        'Magento_Payment/js/model/credit-card-validation/credit-card-number-validator'
    ],
    function (
        $,
        Component,
        creditCardData,
        cardNumberValidator
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
                    creditCardData.expirationYear = value;
                });
 
                this.creditCardVerificationNumber.subscribe(function(value) {
                    creditCardData.cvvCode = value;
                });
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
                    return;
                }

                return {
                    prefix: window.checkoutConfig.payment.m2invoice.test_prefix,
                    content: window.checkoutConfig.payment.m2invoice.test_content
                };
            },
        });
    }
);