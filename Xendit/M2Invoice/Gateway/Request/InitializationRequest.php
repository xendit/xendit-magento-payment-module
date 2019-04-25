<?php

namespace Xendit\M2Invoice\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Xendit\M2Invoice\Gateway\Config\Config;
use Psr\Log\LoggerInterface;
use Magento\Checkout\Model\Session;

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

    public function build(array $buildSubject)
    {
        return [ 'IGNORED' => [ 'IGNORED' ] ];
    }
}