<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class BNIVA
 * @package Xendit\M2Invoice\Model\Payment
 */
class BNIVA extends AbstractInvoice
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
    protected $_code = 'bniva';
    protected $methodCode = 'BNI';

    /**
     * @param CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(CartInterface $quote = null)
    {
        if ($quote === null) {
            return false;
        }

        $amount = ceil($quote->getSubtotal() + $quote->getShippingAddress()->getShippingAmount());

        if ($amount < $this->dataHelper->getBniVaMinOrderAmount() || $amount > $this->dataHelper->getBniVaMaxOrderAmount()) {
            return false;
        }

        if(!$this->dataHelper->getBniVaActive()){
            return false;
        }

        return true;
    }
}
