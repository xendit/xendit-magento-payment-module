<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class Uangme
 * @package Xendit\M2Invoice\Model\Payment
 */
class Uangme extends AbstractInvoice
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
    protected $_code = 'uangme';
    protected $methodCode = 'UANGME';
}
