<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Phrase;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\Method\Logger;
use Xendit\M2Invoice\Helper\ApiRequest;
use Xendit\M2Invoice\Helper\LogDNA;
use Xendit\M2Invoice\Enum\LogDNALevel;

class OVO extends AbstractInvoice
{
    const DEFAULT_EWALLET_TYPE = 'OVO';
    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'ovo';
    protected $_minAmount = 10000;
    protected $_maxAmount = 10000000;
    protected $methodCode = 'OVO';

    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $order = $payment->getOrder();
        $orderId = $order->getRealOrderId();
        $additionalData = $this->getAdditionalData();

        $payment->setIsTransactionPending(true);

        try {
            $args = [
                'external_id' => $this->dataHelper->getExternalId($orderId),
                'amount' => $amount,
                'phone' => $additionalData['phone_number'],
                'ewallet_type' => self::DEFAULT_EWALLET_TYPE,
                'platform_callback_url' => $this->getXenditCallbackUrl()
            ];

            $ewalletPayment = $this->requestEwalletPayment($args);

            if ( isset($ewalletPayment['error_code']) ) {
                if ($ewalletPayment['error_code'] == 'DUPLICATE_PAYMENT_REQUEST_ERROR') {
                    $args = array_replace($args, array(
                        'external_id' => $this->dataHelper->getExternalId($orderId, true)
                    ));
                    $ewalletPayment = $this->requestEwalletPayment($args);
                }

                if (isset($ewalletPayment['error_code'])) {
                    $message = $this->mapOvoErrorCode($ewalletPayment['error_code']);
                    $this->processFailedPayment($payment, $message);
    
                    throw new \Magento\Framework\Exception\LocalizedException(
                        new Phrase($message)
                    );
                }
            }

            $payment->setAdditionalInformation('xendit_ovo_external_id', $ewalletPayment['external_id']);
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            throw new \Magento\Framework\Exception\LocalizedException(
                new Phrase($errorMsg)
            );
        }

        return $this;
    }

    private function requestEwalletPayment($requestData, $isRetried = true)
    {
        $ewalletUrl = $this->dataHelper->getCheckoutUrl() . "/payment/xendit/ewallets";
        $ewalletMethod = \Zend\Http\Request::METHOD_POST;
        $options = [
            'timeout' => 60
        ];

        try {
            $ewalletPayment = $this->apiHelper->request(
                $ewalletUrl,
                $ewalletMethod,
                $requestData,
                null,
                null,
                $options,
                [
                    'x-api-version' => '2020-02-01'
                ]
            );
        } catch (\Exception $e) {
            throw $e;
        }

        return $ewalletPayment;
    }

    private function getAdditionalData()
    {
        static $data = [];
        if (count($data) < 1) {
            $data = (array) $this->getPaymentMethod();
        }

        return $this->elementFromArray($data, 'additional_data');
    }

    private function getPaymentMethod()
    {
        /**
         * @var array $data
         * Holds submitted JSOn data in a PHP associative array
         */
        static $data = [];
        if (count($data) < 1) {
            $data = (array) $this->dataHelper->jsonData();
        }
        return $this->elementFromArray($data, 'paymentMethod');
    }

    private function elementFromArray($data, $element)
    {
        $r = [];
        if (key_exists($element, $data)) {
            $r = (array) $data[$element];
        }

        return $r;
    }

    private function processFailedPayment($payment, $message)
    {
        $payment->setAdditionalInformation('xendit_failure_reason', $message);
    }

    private function mapOvoErrorCode($errorCode)
    {
        switch ( $errorCode ) {
            case 'USER_DID_NOT_AUTHORIZE_THE_PAYMENT':
                return 'Please complete the payment request within 60 seconds.';
            case 'USER_DECLINED_THE_TRANSACTION':
                return 'You rejected the payment request, please try again when needed.';
            case 'PHONE_NUMBER_NOT_REGISTERED':
                return 'Your number is not registered in OVO, please register first or contact OVO Customer Service.';
            case 'EXTERNAL_ERROR':
                return 'There is a technical issue happens on OVO, please contact the merchant to solve this issue.';
            case 'SENDING_TRANSACTION_ERROR':
                return 'Your transaction is not sent to OVO, please try again.';
            case 'EWALLET_APP_UNREACHABLE':
                return 'Do you have OVO app on your phone? Please check your OVO app on your phone and try again.';
            case 'REQUEST_FORBIDDEN_ERROR':
                return 'Your merchant disable OVO payment from his side, please contact your merchant to re-enable it
                    before trying it again.';
            case 'DEVELOPMENT_MODE_PAYMENT_ACKNOWLEDGED':
                return 'Development mode detected. Please refer to our documentations for successful payment
                    simulation';
            default:
                return "Failed to pay with eWallet. Error code: $errorCode";
        }
    }
}
