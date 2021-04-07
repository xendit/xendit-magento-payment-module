<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

class LINKAJA extends AbstractInvoice
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
    protected $_code = 'linkaja';
    protected $methodCode = 'LINKAJA';

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

        if ($amount < $this->dataHelper->getLinkajaMinOrderAmount() || $amount > $this->dataHelper->getLinkajaMaxOrderAmount()) {
            return false;
        }

        if(!$this->dataHelper->getLinkajaActive()){
            return false;
        }

        return true;
    }
}
