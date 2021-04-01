<?php

namespace Xendit\M2Invoice\Model\Handler;

use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Model\Method\AbstractMethod;

/**
 * Class RefundHandler
 * @package Xendit\M2Invoice\Model\Handler
 */
class RefundHandler extends AbstractMethod implements HandlerInterface
{
    /**
     * @param array $handlingSubject
     * @param array $response
     * @return $this|void
     */
    public function handle(array $handlingSubject, array $response)
    {
        return $this;
    }
}
