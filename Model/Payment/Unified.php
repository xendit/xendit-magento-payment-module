<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Class Unified
 * @package Xendit\M2Invoice\Model\Payment
 */
class Unified extends AbstractMethod
{
    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_isInitializeNeeded = true;

    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'unified';
    protected $methodCode = 'UNIFIED';

    /**
     * Check whether payment method is available
     *
     * @param CartInterface|null $quote
     * @return bool
     */
    // FUTURE TODO: refactor Unified and xendit to be one class only
    public function isAvailable(?CartInterface $quote = null): bool
    {
        // Check if Xendit is enabled via the main active configuration
        $isXenditActive = $this->_scopeConfig->getValue(
            'payment/xendit/active',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        return (bool) $isXenditActive;
    }
}
