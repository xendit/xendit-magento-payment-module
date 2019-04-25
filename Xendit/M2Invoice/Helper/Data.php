<?php

namespace Xendit\M2Invoice\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Xendit\M2Invoice\Gateway\Config\Config;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\StoreManagerInterface;

class Data extends AbstractHelper {
    protected $_gatewayConfig;

    protected $_objectManager;

    protected $_storeManager;

    public function __construct(
        Config $gatewayConfig,
        ObjectManagerInterface $objectManager,
        Context $context,
        StoreManagerInterface $storeManager
    ) {
        $this->_gatewayConfig = $gatewayConfig;
        $this->_objectManager = $objectManager;
        $this->_storeManager = $storeManager;
    }

    protected function getGatewayConfig()
    {
        return $this->_gatewayConfig;
    }

    protected function getStoreManager()
    {
        return $this->_storeManager;
    }

    public function getCheckoutUrl()
    {
        return $this->getGatewayConfig()->getGatewayUrl();
    }

    public function getSuccessUrl()
    {
        return $this->getStoreManager()->getStore()->getBaseUrl() . 'xendit/checkout/success';
    }

    public function getFailureUrl($orderId)
    {
        return $this->getStoreManager()->getStore()->getBaseUrl() . "xendit/checkout/failure?order_id=$order_id";
    }

    public function getExternalId($orderId)
    {
        return "magento_xendit_$orderId";
    }
}