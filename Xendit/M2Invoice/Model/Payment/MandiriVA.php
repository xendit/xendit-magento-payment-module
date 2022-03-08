<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class MandiriVA
 * @package Xendit\M2Invoice\Model\Payment
 */
class MandiriVA extends AbstractInvoice
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
    protected $_code = 'mandiriva';
    protected $methodCode = 'MANDIRI';
}
