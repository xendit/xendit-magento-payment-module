<?php

namespace Xendit\M2Invoice\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Xendit\M2Invoice\Gateway\Config\Config;
use Xendit\M2Invoice\Model\Payment\M2Invoice;
use Xendit\M2Invoice\Model\Payment\CC;
use Magento\Payment\Model\CcConfig;

final class ConfigProvider implements ConfigProviderInterface
{
    protected $_m2Invoice;

    protected $_cc;

    protected $_ccConfig;

    public function __construct(
        M2Invoice $m2Invoice,
        CC $cc,
        CcConfig $ccConfig
    ) {
        $this->_m2Invoice = $m2Invoice;
        $this->_cc = $cc;
        $this->_ccConfig = $ccConfig;
    }

    public function getConfig()
    {
        $config = [
            'payment' => [
                Config::CODE => [
                    'xendit_env' => $this->_m2Invoice->getConfigData('xendit_env'),
                    'test_prefix' => $this->_m2Invoice->getConfigData('checkout_test_prefix'),
                    'test_content' => $this->_m2Invoice->getConfigData('checkout_test_content'),
                    'availableTypes' => ['cc' => $this->_ccConfig->getCcAvailableTypes()],
                    'months' => ['cc' => $this->_ccConfig->getCcMonths()],
                    'years' => ['cc' => $this->_ccConfig->getCcYears()],
                    'hasVerification' => $this->_ccConfig->hasVerification()
                ]
            ]
        ];

        return $config;
    }
}