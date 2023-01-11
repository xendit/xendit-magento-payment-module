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
     * @param string $message
     * @return array|string|string[]
     */
    public function mapInvoiceErrorCode(
        $errorCode,
        string $message = ''
    ) {
        $defaultMessage = "Failed to pay with Invoice. Error code: $errorCode";
        switch ($errorCode) {
            case 'UNSUPPORTED_CURRENCY':
            case 'API_VALIDATION_ERROR':
                return !empty($message) ? $message : $defaultMessage;
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
            case 'MERCHANT_NOT_FOUND':
                return 'You are not registered yet to use this payment method.';
            default:
                return $defaultMessage;
        }
    }
}
