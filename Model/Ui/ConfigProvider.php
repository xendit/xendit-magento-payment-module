<?php

namespace Xendit\M2Invoice\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Model\CcConfig;
use Xendit\M2Invoice\Gateway\Config\Config;

use Xendit\M2Invoice\Model\Payment\Xendit;
use Xendit\M2Invoice\Helper\Data as XenditHelper;

/**
 * Class ConfigProvider
 * @package Xendit\M2Invoice\Model\Ui
 */
class ConfigProvider implements ConfigProviderInterface
{
    /**
     * @var Xendit
     */
    private $xendit;

    /**
     * @var CcConfig
     */
    private $ccConfig;

    /**
     * @var XenditHelper
     */
    private $xenditHelper;

    /**
     * ConfigProvider constructor.
     * @param Xendit $xendit
     * @param CcConfig $ccConfig
     * @param XenditHelper $xenditHelper
     */
    public function __construct(
        Xendit $xendit,
        CcConfig $ccConfig,
        XenditHelper $xenditHelper
    ) {
        $this->xendit = $xendit;
        $this->ccConfig = $ccConfig;
        $this->xenditHelper = $xenditHelper;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return [
            'payment' => [
                Config::CODE => [
                    'xendit_env' => $this->xendit->getConfigData('xendit_env'),
                    'test_prefix' => $this->xendit->getConfigData('checkout_test_prefix'),
                    'test_content' => $this->xendit->getConfigData('checkout_test_content'),
                    'public_api_key' => $this->xendit->getPublicApiKey(),
                    'ui_url' => $this->xendit->getUiUrl(),
                    'available_types' => ['cc' => $this->ccConfig->getCcAvailableTypes()],
                    'months' => ['cc' => $this->ccConfig->getCcMonths()],
                    'years' => ['cc' => $this->ccConfig->getCcYears()],
                    'has_verification' => $this->ccConfig->hasVerification()
                ],
                'unified' => [
                    'title' => $this->xenditHelper->getPaymentTitle("unified"),
                    'description' => $this->xenditHelper->getPaymentDescription("unified"),
                    'image' => $this->xenditHelper->getPaymentImage("xendit")
                ],
            ]
        ];
    }
}
