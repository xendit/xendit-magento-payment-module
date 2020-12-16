<?php

namespace Xendit\M2Invoice\Model\Payment;

class OVO extends AbstractInvoice
{
    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'ovo';
    protected $_minAmount = 10000;
    protected $_maxAmount = 10000000;
    protected $methodCode = 'OVO';
}
