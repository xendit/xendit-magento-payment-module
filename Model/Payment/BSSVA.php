<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class BSSVA
 * @package Xendit\M2Invoice\Model\Payment
 */
class BSSVA extends AbstractInvoice
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
    protected $_code = 'bssva';
    protected $methodCode = 'BSS';
}
