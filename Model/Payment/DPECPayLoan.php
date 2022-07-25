<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class DPECPayLoan
 * @package Xendit\M2Invoice\Model\Payment
 */
class DPECPayLoan extends AbstractInvoice
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
    protected $_code = 'dp_ecpay_loan';
    protected $methodCode = 'DP_ECPAY_LOAN';
}
