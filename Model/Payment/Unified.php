<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Payment\Model\Method\AbstractMethod;

/**
 * Class Akulaku
 * @package Xendit\M2Invoice\Model\Payment
 */
class Unified extends AbstractMethod
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
    protected $_code = 'unified';
    protected $methodCode = 'UNIFIED';
}
