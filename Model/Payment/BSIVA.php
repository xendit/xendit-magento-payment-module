<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class BSIVA
 * @package Xendit\M2Invoice\Model\Payment
 */
class BSIVA extends AbstractInvoice
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
    protected $_code = 'bsiva';
    protected $methodCode = 'BSI';
}
