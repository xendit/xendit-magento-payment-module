<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class CC
 * @package Xendit\M2Invoice\Model\Payment
 */
class CC extends AbstractInvoice
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
    protected $_code = 'cc';
    protected $methodCode = 'CC';

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

        if ($amount < $this->dataHelper->getCcMinOrderAmount() || $amount > $this->dataHelper->getCcMaxOrderAmount()) {
            return false;
        }

        if(!$this->dataHelper->getCcActive()){
            return false;
        }

        return true;
    }
}
