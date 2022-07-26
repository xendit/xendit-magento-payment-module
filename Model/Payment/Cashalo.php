<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class Cashalo
 * @package Xendit\M2Invoice\Model\Payment
 */
class Cashalo extends AbstractInvoice
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
    protected $_code = 'cashalo';
    protected $methodCode = 'CASHALO';
}
