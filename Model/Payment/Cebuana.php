<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class Cebuana
 * @package Xendit\M2Invoice\Model\Payment
 */
class Cebuana extends AbstractInvoice
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
    protected $_code = 'cebuana';
    protected $methodCode = 'Cebuana';
}
