<?php

namespace Xendit\M2Invoice\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Xendit\M2Invoice\Gateway\Config\Config;

final class ConfigProvider implements ConfigProviderInterface
{
    protected $_gatewayConfig;

    public function __construct(
        Config $gatewayConfig
    ) {
        $this->_gatewayConfig = $gatewayConfig;
    }

    public function getConfig()
    {
        $config = [
            'payment' => [
                Config::CODE => [
                    'description' => $this->_gatewayConfig->getDescription()
                ]
            ]
        ];

        return $config;
    }
}