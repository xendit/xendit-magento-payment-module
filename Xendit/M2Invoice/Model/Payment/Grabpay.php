<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class GrabPay
 * @package Xendit\M2Invoice\Model\Payment
 */
class Grabpay extends AbstractInvoice
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
    protected $_code = 'grabpay';
    protected $methodCode = 'GRABPAY';

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

        if ($amount < $this->dataHelper->getGrabPayMinOrderAmount() || $amount > $this->dataHelper->getGrabPayMaxOrderAmount()) {
            return false;
        }

        if(!$this->dataHelper->getGrabPayActive()){
            return false;
        }

        if(!$this->dataHelper->getIsActive()){
            return false;
        }

        return true;
    }
}
