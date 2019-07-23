<?php

namespace Xendit\M2Invoice\Model\Payment;

use Xendit\M2Invoice\Enum\LogDNALevel;

class Alfamart extends AbstractInvoice
{
    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'alfamart';
    protected $_minAmount = 10000;
    protected $_maxAmount = 1000000000;
    protected $methodCode = 'ALFAMART';
}
