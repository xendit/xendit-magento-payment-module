<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class DPMlhuillier
 * @package Xendit\M2Invoice\Model\Payment
 */
class DPMlhuillier extends AbstractInvoice
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
    protected $_code = 'dp_mlhuillier';
    protected $methodCode = 'DP_MLHUILLIER';
}
