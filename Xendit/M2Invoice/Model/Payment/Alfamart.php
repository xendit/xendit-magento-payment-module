<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;
/**
 * Class Alfamart
 * @package Xendit\M2Invoice\Model\Payment
 */
class Alfamart extends AbstractInvoice
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
    protected $_code = 'alfamart';
    protected $methodCode = 'ALFAMART';

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

        if ($amount < $this->dataHelper->getAlfamartMinOrderAmount() || $amount > $this->dataHelper->getAlfamartMaxOrderAmount()) {
            return false;
        }

        if(!$this->dataHelper->getAlfamartActive()){
            return false;
        }

        return true;
    }
}
