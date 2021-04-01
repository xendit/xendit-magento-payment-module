<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class Dana
 * @package Xendit\M2Invoice\Model\Payment
 */
class Dana extends AbstractInvoice
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
    protected $_code = 'dana';
    protected $methodCode = 'DANA';

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

        if ($amount < $this->dataHelper->getDanaMinOrderAmount() || $amount > $this->dataHelper->getDanaMinOrderAmount()) {
            return false;
        }

        if(!$this->dataHelper->getDanaActive()){
            return false;
        }

        return true;
    }
}