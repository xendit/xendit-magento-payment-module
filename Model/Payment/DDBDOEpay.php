<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class DDChinaBank
 * @package Xendit\M2Invoice\Model\Payment
 */
class DDBDOEpay extends AbstractInvoice
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
    protected $_code = 'dd_bdo_epay';
    protected $methodCode = 'DD_BDO_EPAY';
}
