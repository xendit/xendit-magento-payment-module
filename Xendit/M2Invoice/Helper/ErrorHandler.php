<?php

namespace Xendit\M2Invoice\Helper;

/**
 * Class ErrorHandler
 * @package Xendit\M2Invoice\Helper
 */
class ErrorHandler
{
    /**
     * @param $errorCode
     * @return string
     */
    public function mapInvoiceErrorCode($errorCode)
    {
        switch ( $errorCode ) {
            case 'API_VALIDATION_ERROR':
                return 'Inputs are failing validation. The errors field contains details about which fields are violating validation.';
            case 'INVALID_JSON_FORMAT':
                return 'The request body is not a valid JSON format.';
            case 'MINIMAL_TRANSFER_AMOUNT_ERROR':
                return 'Could not create invoice because amount is below Rp10000.';
            case 'MAXIMUM_TRANSFER_AMOUNT_ERROR':
                return 'Could not create invoice because amount is above Rp1000000000. If you are configured with Retail Outlets, then amount cannot be more than Rp5000000.';
            case 'NO_COLLECTION_METHODS_ERROR':
                return 'Your account has no payment methods configured (virtual account, credit card). Please contact support and we will set this up for you.';
            case 'UNAVAILABLE_PAYMENT_METHOD_ERROR':
                return 'The payment method choices did not match with the available one on this business.';
            case 'REQUEST_FORBIDDEN_ERROR':
                return 'API key in use does not have necessary permissions to perform the request. Please assign proper permissions for the key.';
            case 'UNIQUE_ACCOUNT_NUMBER_UNAVAILABLE_ERROR':
                return 'There is no available virtual account in your non-fixed virtual account range.';
            case 'CALLBACK_VIRTUAL_ACCOUNT_NOT_FOUND_ERROR':
                return 'Fixed virtual account id that you\'ve specified not found.';
            case 'AVAILABLE_PAYMENT_CODE_NOT_FOUND_ERROR':
                return 'There is no available payment code in your retail outlet range.';
            case 'INVALID_REMINDER_TIME':
                return 'The reminder_time value is not allowed.';
            default:
                return "Failed to pay with Invoice. Error code: $errorCode";
        }
    }

    /**
     * @param $errorCode
     * @return string
     */
    public function mapCardlessCreditErrorCode($errorCode)
    {
        switch ( $errorCode ) {
            case 'MERCHANT_NOT_FOUND':
                return 'You are not registered yet to use this payment method.';
            case 'GENERATE_CHECKOUT_URL_ERROR':
                return 'Your request did not meet the requirement or there is a problem in the Kredivo / Partner server..';
            case 'PHONE_NUMBER_NOT_REGISTERED':
                return 'Your number is not registered in DANA, please register first or contact DANA Customer Service.';
            case 'REQUEST_FORBIDDEN_ERROR':
                return 'API key in use does not have necessary permissions to perform the request. 
                Please assign proper permissions for the key.';
            default:
                return "Failed to pay with Kredivo. Error code: $errorCode";
        }
    }

    /**
     * @param $errorCode
     * @return string
     */
    public function mapQrcodeErrorCode($errorCode)
    {
        switch ( $errorCode ) {
            case 'DUPLICATE_ERROR':
                return 'The payment with the same external_id has already been made before.';
            case 'DATA_NOT_FOUND':
                return 'QRIS merchant not found, please contact our customer success team for activation.';
            case 'REQUEST_FORBIDDEN_ERROR':
                return 'API key in use does not have necessary permissions to perform the request. 
                        Please assign proper permissions for the key';
            case 'API_VALIDATION_ERROR':
                return 'There is invalid input in one of the required request fields.';
            default:
                return "Failed to pay with QRIS. Error code: $errorCode";
        }
    }
}
