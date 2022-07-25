<?php

namespace Xendit\M2Invoice\Gateway\Request;

use Magento\Checkout\Model\Session;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Data\Order\OrderAdapter;
use Magento\Sales\Model\Order;
use Xendit\M2Invoice\Logger\Logger as XenditLogger;
use Xendit\M2Invoice\Gateway\Config\Config;

/**
 * Class InitializationRequest
 * @package Xendit\M2Invoice\Gateway\Request
 */
class InitializationRequest implements BuilderInterface
{
    /**
     * @var Config
     */
    private $gatewayConfig;

    /**
     * @var XenditLogger
     */
    private $xenditLogger;

    /**
     * @var Session
     */
    private $session;

    /**
     * InitializationRequest constructor.
     * @param Config $gatewayConfig
     * @param XenditLogger $xenditLogger
     * @param Session $session
     */
    public function __construct(
        Config $gatewayConfig,
        XenditLogger $xenditLogger,
        Session $session
    ) {
        $this->gatewayConfig = $gatewayConfig;
        $this->xenditLogger = $xenditLogger;
        $this->session = $session;
    }

    /**
     * @param OrderAdapter $order
     * @return bool
     */
    private function validateQuote(OrderAdapter $order)
    {
        $total = $order->getGrandTotalAmount();

        if ($total < 30000) {
            return false;
        }

        return true;
    }

    /**
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        $payment = (isset($buildSubject['payment'])) ? $buildSubject['payment'] : '';
        $stateObject = (isset($buildSubject['stateObject'])) ? $buildSubject['stateObject'] : '';
        $order = ($payment) ? $payment->getOrder() : '';

        if ($order) {
            if ($this->validateQuote($order)) {
                $stateObject->setState(Order::STATE_PENDING_PAYMENT);
                $stateObject->setStatus(Order::STATE_PENDING_PAYMENT);
                $stateObject->setIsNotified(false);
            } else {
                $stateObject->setState(Order::STATE_CANCELED);
                $stateObject->setStatus(Order::STATE_CANCELED);
                $stateObject->setIsNotified(false);
            }
        }

        return [ 'IGNORED' => [ 'IGNORED' ] ];
    }
}
