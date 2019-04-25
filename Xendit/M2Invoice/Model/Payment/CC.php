<?php

namespace Xendit\M2Invoice\Model\Payment;

// use Magento\Payment\Model\CcGenericConfigProvider;

class CC extends \Magento\Payment\Model\Method\AbstractMethod
{
    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'cc';
}