<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class QRIS
 * @package Xendit\M2Invoice\Model\Payment
 */
class QRIS extends AbstractInvoice
{
    protected $_isInitializeNeeded = true;
    protected $_code = 'qris';
    protected $methodCode = 'QRIS';

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

        if ($amount < $this->dataHelper->getQrisMinOrderAmount() || $amount > $this->dataHelper->getQrisMaxOrderAmount()) {
            return false;
        }

        if(!$this->dataHelper->getQrisActive()){
            return false;
        }

        if(!$this->dataHelper->getIsActive()){
            return false;
        }

        return true;
    }
}
