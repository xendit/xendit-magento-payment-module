<?php

namespace Xendit\M2Invoice\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\StoreManagerInterface;
use Xendit\M2Invoice\Model\Payment\M2Invoice;

class Data extends AbstractHelper {
    protected $_objectManager;

    protected $_storeManager;

    protected $_m2Invoice;

    public function __construct(
        ObjectManagerInterface $objectManager,
        Context $context,
        StoreManagerInterface $storeManager,
        M2Invoice $m2Invoice
    ) {
        $this->_objectManager = $objectManager;
        $this->_storeManager = $storeManager;
        $this->_m2Invoice = $m2Invoice;
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
}