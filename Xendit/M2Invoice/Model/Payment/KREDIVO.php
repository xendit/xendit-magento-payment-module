<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class KREDIVO
 * @package Xendit\M2Invoice\Model\Payment
 */
class KREDIVO extends AbstractInvoice
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
    protected $_code = 'kredivo';
    protected $methodCode = 'KREDIVO';

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

        if ($amount < $this->dataHelper->getKredivoMinOrderAmount() || $amount > $this->dataHelper->getKredivoMaxOrderAmount()) {
            return false;
        }

        if(!$this->dataHelper->getKredivoActive()){
            return false;
        }

        return true;
    }
}
