<?php

namespace Xendit\M2Invoice\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Xendit\M2Invoice\Gateway\Config\Config;
use Xendit\M2Invoice\Model\Payment\M2Invoice;

final class ConfigProvider implements ConfigProviderInterface
{
    protected $_m2Invoice;

    public function __construct(
        M2Invoice $m2Invoice
    ) {
        $this->_m2Invoice = $m2Invoice;
    }

    public function getConfig()
    {
        $config = [
            'payment' => [
                Config::CODE => [
                    'xendit_env' => $this->_m2Invoice->getConfigData('xendit_env'),
                    'test_prefix' => $this->_m2Invoice->getConfigData('checkout_test_prefix'),
                    'test_content' => $this->_m2Invoice->getConfigData('checkout_test_content')
                ]
            ]
        ];

        return $config;
    }
}