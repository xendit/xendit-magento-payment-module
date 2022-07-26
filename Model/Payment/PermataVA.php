<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class PermataVA
 * @package Xendit\M2Invoice\Model\Payment
 */
class PermataVA extends AbstractInvoice
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
    protected $_code = 'permatava';
    protected $methodCode = 'PERMATA';
}
