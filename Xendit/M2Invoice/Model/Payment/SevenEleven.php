<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class SevenEleven
 * @package Xendit\M2Invoice\Model\Payment
 */
class SevenEleven extends AbstractInvoice
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
    protected $_code = 'seven_eleven';
    protected $methodCode = '7ELEVEN';

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

        if ($amount < $this->dataHelper->getSevenElevenMinOrderAmount() || $amount > $this->dataHelper->getSevenElevenMaxOrderAmount()) {
            return false;
        }

        if(!$this->dataHelper->getSevenElevenActive()){
            return false;
        }

        if(!$this->dataHelper->getIsActive()){
            return false;
        }

        return true;
    }
}
