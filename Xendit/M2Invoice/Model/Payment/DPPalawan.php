<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class DPPalawan
 * @package Xendit\M2Invoice\Model\Payment
 */
class DPPalawan extends AbstractInvoice
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
    protected $_code = 'dp_palawan';
    protected $methodCode = 'DP_PALAWAN';
}
