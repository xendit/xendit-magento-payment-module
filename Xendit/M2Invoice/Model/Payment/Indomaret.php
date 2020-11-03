<?php

namespace Xendit\M2Invoice\Model\Payment;

class Indomaret extends AbstractInvoice
{
    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'indomaret';
    protected $_minAmount = 10000;
    protected $_maxAmount = 2500000;
    protected $methodCode = 'Indomaret';
}
