<?php

namespace Xendit\M2Invoice\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Model\CcConfig;
use Xendit\M2Invoice\Gateway\Config\Config;
use Xendit\M2Invoice\Model\Payment\CC;
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
     * @var CC
     */
    private $cc;

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
     * @param CC $cc
     * @param CcConfig $ccConfig
     * @param XenditHelper $xenditHelper
     */
    public function __construct(
        Xendit $xendit,
        CC $cc,
        CcConfig $ccConfig,
        XenditHelper $xenditHelper
    ) {
        $this->xendit = $xendit;
        $this->cc = $cc;
        $this->ccConfig = $ccConfig;
        $this->xenditHelper = $xenditHelper;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        $config = [
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
                'dana' => [
                    'title' => $this->xenditHelper->getDanaTitle(),
                    'min_order_amount' => $this->xenditHelper->getDanaMinOrderAmount(),
                    'max_order_amount' => $this->xenditHelper->getDanaMaxOrderAmount(),
                    'description' => $this->xenditHelper->getDanaDescription()
                ],
                'ovo' => [
                    'title' => $this->xenditHelper->getOvoTitle(),
                    'min_order_amount' => $this->xenditHelper->getOvoMinOrderAmount(),
                    'max_order_amount' => $this->xenditHelper->getOvoMaxOrderAmount(),
                    'description' => $this->xenditHelper->getOvoDescription()
                ],
                'linkaja' => [
                    'title' => $this->xenditHelper->getLinkajaTitle(),
                    'min_order_amount' => $this->xenditHelper->getLinkajaMinOrderAmount(),
                    'max_order_amount' => $this->xenditHelper->getLinkajaMaxOrderAmount(),
                    'description' => $this->xenditHelper->getLinkajaDescription()
                ],
                'alfamart' => [
                    'title' => $this->xenditHelper->getAlfamartTitle(),
                    'min_order_amount' => $this->xenditHelper->getAlfamartMinOrderAmount(),
                    'max_order_amount' => $this->xenditHelper->getAlfamartMaxOrderAmount(),
                    'description' => $this->xenditHelper->getAlfamartDescription()
                ],
                'indomaret' => [
                    'title' => $this->xenditHelper->getIndomaratTitle(),
                    'min_order_amount' => $this->xenditHelper->getIndomaratMinOrderAmount(),
                    'max_order_amount' => $this->xenditHelper->getIndomaratMaxOrderAmount(),
                    'description' => $this->xenditHelper->getIndomaratDescription()
                ],
                'bcava' => [
                    'title' => $this->xenditHelper->getBcaVaTitle(),
                    'min_order_amount' => $this->xenditHelper->getBcaVaMinOrderAmount(),
                    'max_order_amount' => $this->xenditHelper->getBcaVaMaxOrderAmount(),
                    'description' => $this->xenditHelper->getBcaVaDescription()
                ],
                'briva' => [
                    'title' => $this->xenditHelper->getBriVaTitle(),
                    'min_order_amount' => $this->xenditHelper->getBriVaMinOrderAmount(),
                    'max_order_amount' => $this->xenditHelper->getBriVaMaxOrderAmount(),
                    'description' => $this->xenditHelper->getBriVaDescription()
                ],
                'bniva' => [
                    'title' => $this->xenditHelper->getBniVaTitle(),
                    'min_order_amount' => $this->xenditHelper->getBniVaMinOrderAmount(),
                    'max_order_amount' => $this->xenditHelper->getBniVaMaxOrderAmount(),
                    'description' => $this->xenditHelper->getBniVaDescription()
                ],
                'qr_codes' => [
                    'title' => $this->xenditHelper->getQrCodesTitle(),
                    'min_order_amount' => $this->xenditHelper->getQrCodesMinOrderAmount(),
                    'max_order_amount' => $this->xenditHelper->getQrCodesMaxOrderAmount(),
                    'description' => $this->xenditHelper->getQrCodesDescription()
                ],
                'dd_bri' => [
                    'title' => $this->xenditHelper->getDdBriTitle(),
                    'min_order_amount' => $this->xenditHelper->getDdBriMinOrderAmount(),
                    'max_order_amount' => $this->xenditHelper->getDdBriMaxOrderAmount(),
                    'description' => $this->xenditHelper->getDdBriDescription()
                ],
                'permatava' => [
                    'title' => $this->xenditHelper->getPermataVaTitle(),
                    'min_order_amount' => $this->xenditHelper->getPermataVaMinOrderAmount(),
                    'max_order_amount' => $this->xenditHelper->getPermataVaMaxOrderAmount(),
                    'description' => $this->xenditHelper->getPermataVaDescription()
                ],
                'mandiriva' => [
                    'title' => $this->xenditHelper->getMandiriVaTitle(),
                    'min_order_amount' => $this->xenditHelper->getMandiriVaMinOrderAmount(),
                    'max_order_amount' => $this->xenditHelper->getMandiriVaMaxOrderAmount(),
                    'description' => $this->xenditHelper->getMandiriVaDescription()
                ],
                'kredivo' => [
                    'title' => $this->xenditHelper->getKredivoTitle(),
                    'min_order_amount' => $this->xenditHelper->getKredivoMinOrderAmount(),
                    'max_order_amount' => $this->xenditHelper->getKredivoMaxOrderAmount(),
                    'description' => $this->xenditHelper->getKredivoDescription()
                ],
                'cc' => [
                    'title' => $this->xenditHelper->getCcTitle(),
                    'min_order_amount' => $this->xenditHelper->getCcMinOrderAmount(),
                    'max_order_amount' => $this->xenditHelper->getCcMaxOrderAmount(),
                    'description' => $this->xenditHelper->getCcDescription()
                ],
                'cc_subscription' => [
                    'title' => $this->xenditHelper->getCcSubscriptionTitle(),
                    'min_order_amount' => $this->xenditHelper->getCcSubscriptionMinOrderAmount(),
                    'max_order_amount' => $this->xenditHelper->getCcSubscriptionMaxOrderAmount(),
                    'description' => $this->xenditHelper->getCcSubscriptionDescription(),
                    'interval' => $this->xenditHelper->getCcSubscriptionInterval(),
                    'interval_count' => $this->xenditHelper->getCcSubscriptionIntervalCount()
                ]
            ]
        ];

        return $config;
    }
}
