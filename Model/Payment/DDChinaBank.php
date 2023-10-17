<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class DDChinaBank
 * @package Xendit\M2Invoice\Model\Payment
 */
class DDChinaBank extends AbstractInvoice
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
    protected $_code = 'dd_chinabank';
    protected $methodCode = 'DD_CHINABANK';
}
