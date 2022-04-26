<?php

namespace Xendit\M2Invoice\Logger;

use Monolog\Logger;
use Magento\Framework\Logger\Handler\Base;

/**
 * Class Handler
 * @package Xendit\M2Invoice\Logger
 */
class Handler extends Base
{
    protected $loggerType = Logger::INFO;

    protected $fileName = '/var/log/xendit_payment.log';
}
