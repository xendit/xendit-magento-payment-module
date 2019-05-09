<?php

namespace Xendit\M2Invoice\Helper;

use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Store\Model\StoreManagerInterface;
use Xendit\M2Invoice\Model\Payment\M2Invoice;

class Data extends AbstractHelper {
    protected $_objectManager;

    protected $_storeManager;

    protected $_m2Invoice;

    protected $_fileSystem;

    public function __construct(
        ObjectManagerInterface $objectManager,
        Context $context,
        StoreManagerInterface $storeManager,
        M2Invoice $m2Invoice,
        File $fileSystem
    ) {
        $this->_objectManager = $objectManager;
        $this->_storeManager = $storeManager;
        $this->_m2Invoice = $m2Invoice;
        $this->_fileSystem = $fileSystem;

        parent::__construct($context);
    }

    protected function getStoreManager()
    {
        return $this->_storeManager;
    }

    public function getCheckoutUrl()
    {
        return $this->_m2Invoice->getConfigData('xendit_url');
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

    public function getExternalId($orderId)
    {
        return "magento_xendit_$orderId";
    }

    public function getValidationKey()
    {
        return $this->_m2Invoice->getValidationKey();
    }

    public function getApiKey()
    {
        return $this->_m2Invoice->getApiKey();
    }

    public function getPublicApiKey()
    {
        return $this->_m2Invoice->getPublicApiKey();
    }

    public function getEnvironment()
    {
        return $this->_m2Invoice->getEnvironment();
    }

    public function jsonData()
    {
        $inputs = json_decode((string) $this->_fileSystem->fileGetContents((string)'php://input'), (bool) true);
        $methods = $this->_request->getServer('REQUEST_METHOD');
        
        if (empty($inputs) === true && $methods === 'POST') {
            $post = $this->_request->getPostValue();
                       
            if (array_key_exists('payment', $post)) {
                $inputs['paymentMethod']['additional_data'] = $post['payment'];
            }
        }

        return (array) $inputs;
    }
}