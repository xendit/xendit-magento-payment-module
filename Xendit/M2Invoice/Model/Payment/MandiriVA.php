<?php

namespace Xendit\M2Invoice\Model\Payment;

class MandiriVA extends \Magento\Payment\Model\Method\AbstractMethod
{
    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'mandiriva';
}