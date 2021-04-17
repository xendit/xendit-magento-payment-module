<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class BCAVA
 * @package Xendit\M2Invoice\Model\Payment
 */
class BCAVA extends AbstractInvoice
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
    protected $_code = 'bcava';
    protected $methodCode = 'BCA';

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

        if ($amount < $this->dataHelper->getBcaVaMinOrderAmount() || $amount > $this->dataHelper->getBcaVaMaxOrderAmount()) {
            return false;
        }

        if(!$this->dataHelper->getBcaVaActive()){
            return false;
        }

        return true;
    }
}
