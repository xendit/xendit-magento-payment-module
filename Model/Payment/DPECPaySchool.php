<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class DPECPaySchool
 * @package Xendit\M2Invoice\Model\Payment
 */
class DPECPaySchool extends AbstractInvoice
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
    protected $_code = 'dp_ecpay_school';
    protected $methodCode = 'DP_ECPAY_SCHOOL';
}
