<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class DDBRI
 * @package Xendit\M2Invoice\Model\Payment
 */
class DDBRI extends AbstractInvoice
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
    protected $_code = 'dd_bri';
    protected $methodCode = 'DD_BRI';

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

        if ($amount < $this->dataHelper->getDdBriMinOrderAmount() || $amount > $this->dataHelper->getDdBriMaxOrderAmount()) {
            return false;
        }

        if(!$this->dataHelper->getDdBriActive()){
            return false;
        }

        if(!$this->dataHelper->getIsActive()){
            return false;
        }

        return true;
    }
}
