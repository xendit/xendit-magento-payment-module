<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class DDBPI
 * @package Xendit\M2Invoice\Model\Payment
 */
class DDBPI extends AbstractInvoice
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
    protected $_code = 'dd_bpi';
    protected $methodCode = 'DD_BPI';
}
