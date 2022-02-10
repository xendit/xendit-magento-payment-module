<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class Billease
 * @package Xendit\M2Invoice\Model\Payment
 */
class Billease extends AbstractInvoice
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
    protected $_code = 'billease';
    protected $methodCode = 'BILLEASE';

    /**
     * @param CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(CartInterface $quote = null)
    {
        if ($quote === null) {
            return false;
        }

        if (!$this->isAvailableOnCurrency()) {
            return false;
        }

        $amount = ceil($quote->getSubtotal() + $quote->getShippingAddress()->getShippingAmount());

        if ($amount < $this->dataHelper->getBillEaseMinOrderAmount() || $amount > $this->dataHelper->getBillEaseMaxOrderAmount()) {
            return false;
        }

        if(!$this->dataHelper->getBillEaseActive()){
            return false;
        }

        if(!$this->dataHelper->getIsActive()){
            return false;
        }

        return true;
    }
}
