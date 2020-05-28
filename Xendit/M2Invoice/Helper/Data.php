<?php

namespace Xendit\M2Invoice\Helper;

use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Store\Model\StoreManagerInterface;
use Xendit\M2Invoice\Model\Payment\M2Invoice;

class Data extends AbstractHelper
{
    private $objectManager;

    private $storeManager;

    private $m2Invoice;

    private $fileSystem;

    public function __construct(
        ObjectManagerInterface $objectManager,
        Context $context,
        StoreManagerInterface $storeManager,
        M2Invoice $m2Invoice,
        File $fileSystem
    ) {
        $this->objectManager = $objectManager;
        $this->storeManager = $storeManager;
        $this->m2Invoice = $m2Invoice;
        $this->fileSystem = $fileSystem;

        parent::__construct($context);
    }

    protected function getStoreManager()
    {
        return $this->storeManager;
    }

    public function getCheckoutUrl()
    {
        return $this->m2Invoice->getConfigData('xendit_url');
    }

    public function getSuccessUrl()
    {
        return $this->getStoreManager()->getStore()->getBaseUrl() . 'xendit/checkout/success';
    }

    public function getFailureUrl($orderId)
    {
        return $this->getStoreManager()->getStore()->getBaseUrl() . "xendit/checkout/failure?order_id=$orderId";
    }

    public function getThreeDSResultUrl($orderId)
    {
        return $this->getStoreManager()->getStore()->getBaseUrl() . "xendit/checkout/threedsresult?order_id=$orderId";
    }

    public function getExternalId($orderId, $duplicate = false)
    {
        $storeName = substr(preg_replace("/[^a-z0-9]/mi", "", $this->getStoreManager()->getStore()->getName()), 0, 20);

        $defaultExtId = "magento-xendit-$storeName-$orderId";

        if ($duplicate) {
            return uniqid() . "-" . $defaultExtId;
        }

        return $defaultExtId;
    }

    public function getApiKey()
    {
        return $this->m2Invoice->getApiKey();
    }

    public function getPublicApiKey()
    {
        return $this->m2Invoice->getPublicApiKey();
    }

    public function getEnvironment()
    {
        return $this->m2Invoice->getEnvironment();
    }

    public function getCardPaymentType()
    {
        return $this->m2Invoice->getCardPaymentType();
    }

    public function getAllowedMethod()
    {
        return $this->m2Invoice->getAllowedMethod();
    }

    public function getChosenMethods()
    {
        return $this->m2Invoice->getChosenMethods();
    }

    public function getEnabledPromo()
    {
        return $this->m2Invoice->getEnabledPromo();
    }

    public function jsonData()
    {
        $inputs = json_decode((string) $this->fileSystem->fileGetContents((string)'php://input'), (bool) true);
        $methods = $this->_request->getServer('REQUEST_METHOD');
        
        if (empty($inputs) === true && $methods === 'POST') {
            $post = $this->_request->getPostValue();
                       
            if (array_key_exists('payment', $post)) {
                $inputs['paymentMethod']['additional_data'] = $post['payment'];
            }
        }

        return (array) $inputs;
    }

    /**
     * Map card's failure reason to more detailed explanation based on current insight.
     *
     * @param $failureReason
     * @return string
     */
    public function failureReasonInsight($failureReason)
    {
        switch ($failureReason) {
            case 'CARD_DECLINED':
            case 'STOLEN_CARD': return 'CARD_DECLINED - The bank that issued this card declined the payment but didn\'t tell us why.
                Try another card, or try calling your bank to ask why the card was declined.';
            case 'INSUFFICIENT_BALANCE': return "$failureReason - Your bank declined this payment due to insufficient balance. Ensure
                that sufficient balance is available, or try another card";
            case 'INVALID_CVN': return "$failureReason - Your bank declined the payment due to incorrect card details entered. Try to
                enter your card details again, including expiration date and CVV";
            case 'INACTIVE_CARD': return "$failureReason - This card number does not seem to be enabled for eCommerce payments. Try
                another card that is enabled for eCommerce, or ask your bank to enable eCommerce payments for your card.";
            case 'EXPIRED_CARD': return "$failureReason - Your bank declined the payment due to the card being expired. Please try
                another card that has not expired.";
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
            default: return $failureReason;
        }
    }

    /**
     * Map Magento sales rule action to Xendit's standard type
     *
     * @param $type
     * @return string
     */
    public function mapSalesRuleType($type)
    {
        switch ($type) {
            case 'to_percent':
            case 'by_percent':
                return 'PERCENTAGE';
            case 'to_fixed':
            case 'by_fixed':
                return 'FIXED';
            default:
                return $type;
        }
    }
    
    public function xenditPaymentMethod( $payment ){
        
        //methode name => frontend routing
        $listPayment = [
            "cc" => "cc",
            "cchosted" => "cchosted",
            "bcava" => "bca",
            "bniva" => "bni",
            "briva" => "bri",
            "mandiriva" => "mandiri",
            "permatava" => "permata",
            "alfamart" => "alfamart",
            "ovo" => "ovo"
        ];

        $response = FALSE;
        if( !!array_key_exists($payment, $listPayment) ){
            $response = $listPayment[$payment];
        }

        return $response; 
    }
}
