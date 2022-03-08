<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class Paymaya
 * @package Xendit\M2Invoice\Model\Payment
 */
class Paymaya extends AbstractInvoice
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
    protected $_code = 'paymaya';
    protected $methodCode = 'PAYMAYA';
}
