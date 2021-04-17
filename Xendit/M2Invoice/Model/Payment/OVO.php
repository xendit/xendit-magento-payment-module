<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class OVO
 * @package Xendit\M2Invoice\Model\Payment
 */
class OVO extends AbstractInvoice
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
    protected $_code = 'ovo';
    protected $methodCode = 'OVO';

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

        if ($amount < $this->dataHelper->getOvoMinOrderAmount() || $amount > $this->dataHelper->getOvoMaxOrderAmount()) {
            return false;
        }

        if(!$this->dataHelper->getOvoActive()){
            return false;
        }

        return true;
    }
}
