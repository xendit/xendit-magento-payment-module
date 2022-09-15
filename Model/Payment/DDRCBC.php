<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class DDRCBC
 * @package Xendit\M2Invoice\Model\Payment
 */
class DDRCBC extends AbstractInvoice
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
    protected $_code = 'dd_rcbc';
    protected $methodCode = 'DD_RCBC';
}
