<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class LBC
 * @package Xendit\M2Invoice\Model\Payment
 */
class LBC extends AbstractInvoice
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
    protected $_code = 'lbc';
    protected $methodCode = 'LBC';
}
