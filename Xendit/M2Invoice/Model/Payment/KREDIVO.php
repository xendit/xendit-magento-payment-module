<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class KREDIVO
 * @package Xendit\M2Invoice\Model\Payment
 */
class KREDIVO extends AbstractInvoice
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
    protected $_code = 'kredivo';
    protected $methodCode = 'KREDIVO';
}
