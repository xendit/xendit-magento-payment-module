<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class SevenEleven
 * @package Xendit\M2Invoice\Model\Payment
 */
class SevenEleven extends AbstractInvoice
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
    protected $_code = 'seven_eleven';
    protected $methodCode = '7ELEVEN';
}
