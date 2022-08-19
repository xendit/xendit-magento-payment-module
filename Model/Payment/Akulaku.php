<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class Akulaku
 * @package Xendit\M2Invoice\Model\Payment
 */
class Akulaku extends AbstractInvoice
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
    protected $_code = 'akulaku';
    protected $methodCode = 'AKULAKU';
}
