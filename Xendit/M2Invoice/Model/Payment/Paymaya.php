<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class Paymaya
 * @package Xendit\M2Invoice\Model\Payment
 */
class Paymaya extends AbstractInvoice
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
    protected $_code = 'paymaya';
    protected $methodCode = 'PAYMAYA';

    /**
     * @param CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(CartInterface $quote = null)
    {
        if ($quote === null) {
            return false;
        }

        if ($this->getCurrency() != "PHP") {
            return false;
        }

        $amount = ceil($quote->getSubtotal() + $quote->getShippingAddress()->getShippingAmount());

        if ($amount < $this->dataHelper->getPermataVaMinOrderAmount() || $amount > $this->dataHelper->getPermataVaMaxOrderAmount()) {
            return false;
        }

        if(!$this->dataHelper->getPayMayaActive()){
            return false;
        }

        if(!$this->dataHelper->getIsActive()){
            return false;
        }

        return true;
    }
}
