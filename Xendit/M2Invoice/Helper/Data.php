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
        $defaultExtId = "magento_xendit_$orderId";

        if ($duplicate) {
            return $defaultExtId . "_" . uniqid();
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
            case 'STOLEN_CARD': return 'The bank that issued this card declined the payment but didn\'t tell us why.
                Try another card, or try calling your bank to ask why the card was declined.';
            case 'INSUFFICIENT_BALANCE': return 'Your bank declined this payment due to insufficient balance. Ensure
                that sufficient balance is available, or try another card';
            case 'INVALID_CVN': return 'Your bank declined the payment due to incorrect card details entered. Try to
                enter your card details again, including expiration date and CVV';
            case 'INACTIVE_CARD': return 'This card number does not seem to be enabled for eCommerce payments. Try
                another card that is enabled for eCommerce, or ask your bank to enable eCommerce payments for your card.';
            case 'EXPIRED_CARD': return 'Your bank declined the payment due to the card being expired. Please try
                another card that has not expired.';
            default: return $failureReason;
        }
    }
}
