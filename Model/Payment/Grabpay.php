<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class GrabPay
 * @package Xendit\M2Invoice\Model\Payment
 */
class Grabpay extends AbstractInvoice
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
    protected $_code = 'grabpay';
    protected $methodCode = 'GRABPAY';
}
