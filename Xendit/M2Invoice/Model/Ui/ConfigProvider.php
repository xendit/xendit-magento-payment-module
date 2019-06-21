<?php

namespace Xendit\M2Invoice\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Model\CcConfig;
use Xendit\M2Invoice\Gateway\Config\Config;
use Xendit\M2Invoice\Model\Payment\CC;
use Xendit\M2Invoice\Model\Payment\M2Invoice;

final class ConfigProvider implements ConfigProviderInterface
{
    private $m2Invoice;

    private $cc;

    private $ccConfig;

    public function __construct(
        M2Invoice $m2Invoice,
        CC $cc,
        CcConfig $ccConfig
    ) {
        $this->m2Invoice = $m2Invoice;
        $this->cc = $cc;
        $this->ccConfig = $ccConfig;
    }

    public function getConfig()
    {
        $config = [
            'payment' => [
                Config::CODE => [
                    'xendit_env' => $this->m2Invoice->getConfigData('xendit_env'),
                    'test_prefix' => $this->m2Invoice->getConfigData('checkout_test_prefix'),
                    'test_content' => $this->m2Invoice->getConfigData('checkout_test_content'),
                    'public_api_key' => $this->m2Invoice->getPublicApiKey(),
                    'availableTypes' => ['cc' => $this->ccConfig->getCcAvailableTypes()],
                    'months' => ['cc' => $this->ccConfig->getCcMonths()],
                    'years' => ['cc' => $this->ccConfig->getCcYears()],
                    'hasVerification' => $this->ccConfig->hasVerification()
                ]
            ]
        ];

        return $config;
    }
}
