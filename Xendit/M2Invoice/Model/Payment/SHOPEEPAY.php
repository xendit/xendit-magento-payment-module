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
}
