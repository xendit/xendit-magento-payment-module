<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;
/**
 * Class Alfamart
 * @package Xendit\M2Invoice\Model\Payment
 */
class Alfamart extends AbstractInvoice
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
    protected $_code = 'alfamart';
    protected $methodCode = 'ALFAMART';
}
