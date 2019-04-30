<?php

namespace Xendit\M2Invoice\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Xendit\M2Invoice\Gateway\Config\Config;
use Psr\Log\LoggerInterface;
use Magento\Checkout\Model\Session;
use Magento\Payment\Gateway\Data\Order\OrderAdapter;
use Magento\Sales\Model\Order;

class InitializationRequest implements BuilderInterface
{
    private $_gatewayConfig;
    private $_logger;
    private $_session;

    public function __construct(
        Config $gatewayConfig,
        LoggerInterface $logger,
        Session $session
    ) {
        $this->_gatewayConfig = $gatewayConfig;
        $this->_logger = $logger;
        $this->_session = $session;
    }

    private function validateQuote(OrderAdapter $order)
    {
        $total = $order->getGrandTotalAmount();

        if ($total < 30000) {
            return false;
        }

        return true;
    }

    public function build(array $buildSubject)
    {
        $payment = $buildSubject['payment'];
        $stateObject = $buildSubject['stateObject'];

        $order = $payment->getOrder();

        if ($this->validateQuote($order)) {
            $stateObject->setState(Order::STATE_PENDING_PAYMENT);
            $stateObject->setStatus(Order::STATE_PENDING_PAYMENT);
            $stateObject->setIsNotified(false);
        } else {
            $stateObject->setState(Order::STATE_CANCELED);
            $stateObject->setStatus(Order::STATE_CANCELED);
            $stateObject->setIsNotified(false);
        }

        return [ 'IGNORED' => [ 'IGNORED' ] ];
    }
}