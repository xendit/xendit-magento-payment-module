<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class BRIVA
 * @package Xendit\M2Invoice\Model\Payment
 */
class BRIVA extends AbstractInvoice
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
    protected $_code = 'briva';
    protected $methodCode = 'BRI';

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

        if ($amount < $this->dataHelper->getBriVaMinOrderAmount() || $amount > $this->dataHelper->getBriVaMaxOrderAmount()) {
            return false;
        }

        if(!$this->dataHelper->getBriVaActive()){
            return false;
        }

        return true;
    }
}
