<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class Indomaret
 * @package Xendit\M2Invoice\Model\Payment
 */
class Indomaret extends AbstractInvoice
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
    protected $_code = 'indomaret';
    protected $methodCode = 'Indomaret';

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

        if ($amount < $this->dataHelper->getIndomaratMinOrderAmount() || $amount > $this->dataHelper->getIndomaratMaxOrderAmount()) {
            return false;
        }

        if(!$this->dataHelper->getIndomaratActive()){
            return false;
        }

        return true;
    }
}
