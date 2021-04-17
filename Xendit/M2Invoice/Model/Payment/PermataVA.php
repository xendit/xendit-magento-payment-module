<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class PermataVA
 * @package Xendit\M2Invoice\Model\Payment
 */
class PermataVA extends AbstractInvoice
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
    protected $_code = 'permatava';
    protected $methodCode = 'PERMATA';

    /**
     * @param CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(CartInterface $quote = null)
    {
        if ($quote === null) {
            return false;
        }

        if ($this->getCurrency() != "IDR") {
            return false;
        }

        $amount = ceil($quote->getSubtotal() + $quote->getShippingAddress()->getShippingAmount());

        if ($amount < $this->dataHelper->getPermataVaMinOrderAmount() || $amount > $this->dataHelper->getPermataVaMaxOrderAmount()) {
            return false;
        }

        if(!$this->dataHelper->getPermataVaActive()){
            return false;
        }

        return true;
    }
}
