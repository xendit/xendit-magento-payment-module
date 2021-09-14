<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class SHOPEEPAY
 * @package Xendit\M2Invoice\Model\Payment
 */
class SHOPEEPAY extends AbstractInvoice
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
    protected $_code = 'shopeepay';
    protected $methodCode = 'SHOPEEPAY';

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

        if ($amount < $this->dataHelper->getShopeePayMinOrderAmount() || $amount > $this->dataHelper->getShopeePayMaxOrderAmount()) {
            return false;
        }

        if(!$this->dataHelper->getShopeePayActive()){
            return false;
        }

        return true;
    }
}
