<?php

namespace Xendit\M2Invoice\Model\Payment;

class M2Invoice extends \Magento\Payment\Model\Method\AbstractMethod
{
    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'm2invoice';
}