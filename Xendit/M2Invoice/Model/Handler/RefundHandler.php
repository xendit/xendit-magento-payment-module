<?php

namespace Xendit\M2Invoice\Model\Handler;

use Magento\Payment\Gateway\Response\HandlerInterface;

class RefundHandler extends \Magento\Payment\Model\Method\AbstractMethod implements HandlerInterface
{
    public function handle(array $handlingSubject, array $response)
    {
        return $this;
    }
}