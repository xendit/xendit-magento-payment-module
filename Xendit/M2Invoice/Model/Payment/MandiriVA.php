<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class MandiriVA
 * @package Xendit\M2Invoice\Model\Payment
 */
class MandiriVA extends AbstractInvoice
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
    protected $_code = 'mandiriva';
    protected $methodCode = 'MANDIRI';

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

        if ($amount < $this->dataHelper->getMandiriVaMinOrderAmount() || $amount > $this->dataHelper->getMandiriVaMaxOrderAmount()) {
            return false;
        }

        if(!$this->dataHelper->getMandiriVaActive()){
            return false;
        }

        if(!$this->dataHelper->getIsActive()){
            return false;
        }

        return true;
    }
}
