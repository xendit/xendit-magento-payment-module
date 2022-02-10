<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class Cebuana
 * @package Xendit\M2Invoice\Model\Payment
 */
class Cebuana extends AbstractInvoice
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
    protected $_code = 'cebuana';
    protected $methodCode = 'Cebuana';

    /**
     * @param CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(CartInterface $quote = null)
    {
        if ($quote === null || !$this->isAvailableOnCurrency()) {
            return false;
        }

        $amount = ceil($quote->getSubtotal() + $quote->getShippingAddress()->getShippingAmount());
        if ($amount < $this->dataHelper->getPaymentMinOrderAmount($_code) || $amount > $this->dataHelper->getPaymentMaxOrderAmount($_code)) {
            return false;
        }

        if (!$this->dataHelper->getIsPaymentActive($_code) || !$this->dataHelper->getIsActive()) {
            return false;
        }

        return true;
    }
}
