<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class AstraPay
 * @package Xendit\M2Invoice\Model\Payment
 */
class AstraPay extends AbstractInvoice
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
    protected $_code = 'astrapay';
    protected $methodCode = 'ASTRAPAY';
}
