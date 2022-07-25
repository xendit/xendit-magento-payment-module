<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class QRIS
 * @package Xendit\M2Invoice\Model\Payment
 */
class QRIS extends AbstractInvoice
{
    protected $_isInitializeNeeded = true;
    protected $_code = 'qris';
    protected $methodCode = 'QRIS';
}
