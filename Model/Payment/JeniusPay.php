<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class JeniusPay
 * @package Xendit\M2Invoice\Model\Payment
 */
class JeniusPay extends AbstractInvoice
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
    protected $_code = 'jeniuspay';
    protected $methodCode = 'JENIUSPAY';
}
