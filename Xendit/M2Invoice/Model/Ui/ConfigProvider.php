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
                    'description' => $this->xenditHelper->getDanaDescription(),
                    'image' => $this->xenditHelper->getPaymentImage('dana')
                ],
                'ovo' => [
                    'title' => $this->xenditHelper->getOvoTitle(),
                    'min_order_amount' => $this->xenditHelper->getOvoMinOrderAmount(),
                    'max_order_amount' => $this->xenditHelper->getOvoMaxOrderAmount(),
                    'description' => $this->xenditHelper->getOvoDescription(),
                    'image' => $this->xenditHelper->getPaymentImage('ovo')
                ],
                'shopeepay' => [
                    'title' => $this->xenditHelper->getShopeePayTitle(),
                    'min_order_amount' => $this->xenditHelper->getShopeePayMinOrderAmount(),
                    'max_order_amount' => $this->xenditHelper->getShopeePayMaxOrderAmount(),
                    'description' => $this->xenditHelper->getShopeePayDescription(),
                    'image' => $this->xenditHelper->getPaymentImage('shopeepay')
                ],
                'linkaja' => [
                    'title' => $this->xenditHelper->getLinkajaTitle(),
                    'min_order_amount' => $this->xenditHelper->getLinkajaMinOrderAmount(),
                    'max_order_amount' => $this->xenditHelper->getLinkajaMaxOrderAmount(),
                    'description' => $this->xenditHelper->getLinkajaDescription(),
                    'image' => $this->xenditHelper->getPaymentImage('linkaja')
                ],
                'alfamart' => [
                    'title' => $this->xenditHelper->getAlfamartTitle(),
                    'min_order_amount' => $this->xenditHelper->getAlfamartMinOrderAmount(),
                    'max_order_amount' => $this->xenditHelper->getAlfamartMaxOrderAmount(),
                    'description' => $this->xenditHelper->getAlfamartDescription(),
                    'image' => $this->xenditHelper->getPaymentImage('alfamart')
                ],
                'indomaret' => [
                    'title' => $this->xenditHelper->getIndomaretTitle(),
                    'min_order_amount' => $this->xenditHelper->getIndomaretMinOrderAmount(),
                    'max_order_amount' => $this->xenditHelper->getIndomaretMaxOrderAmount(),
                    'description' => $this->xenditHelper->getIndomaretDescription(),
                    'image' => $this->xenditHelper->getPaymentImage('indomaret')
                ],
                'bcava' => [
                    'title' => $this->xenditHelper->getBcaVaTitle(),
                    'min_order_amount' => $this->xenditHelper->getBcaVaMinOrderAmount(),
                    'max_order_amount' => $this->xenditHelper->getBcaVaMaxOrderAmount(),
                    'description' => $this->xenditHelper->getBcaVaDescription(),
                    'image' => $this->xenditHelper->getPaymentImage('bcava')
                ],
                'briva' => [
                    'title' => $this->xenditHelper->getBriVaTitle(),
                    'min_order_amount' => $this->xenditHelper->getBriVaMinOrderAmount(),
                    'max_order_amount' => $this->xenditHelper->getBriVaMaxOrderAmount(),
                    'description' => $this->xenditHelper->getBriVaDescription(),
                    'image' => $this->xenditHelper->getPaymentImage('briva')
                ],
                'bniva' => [
                    'title' => $this->xenditHelper->getBniVaTitle(),
                    'min_order_amount' => $this->xenditHelper->getBniVaMinOrderAmount(),
                    'max_order_amount' => $this->xenditHelper->getBniVaMaxOrderAmount(),
                    'description' => $this->xenditHelper->getBniVaDescription(),
                    'image' => $this->xenditHelper->getPaymentImage('bniva')
                ],
                'qris' => [
                    'title' => $this->xenditHelper->getQrisTitle(),
                    'min_order_amount' => $this->xenditHelper->getQrisMinOrderAmount(),
                    'max_order_amount' => $this->xenditHelper->getQrisMaxOrderAmount(),
                    'description' => $this->xenditHelper->getQrisDescription(),
                    'image' => $this->xenditHelper->getPaymentImage('qris')
                ],
                'dd_bri' => [
                    'title' => $this->xenditHelper->getDdBriTitle(),
                    'min_order_amount' => $this->xenditHelper->getDdBriMinOrderAmount(),
                    'max_order_amount' => $this->xenditHelper->getDdBriMaxOrderAmount(),
                    'description' => $this->xenditHelper->getDdBriDescription(),
                    'image' => $this->xenditHelper->getPaymentImage('dd_bri')
                ],
                'permatava' => [
                    'title' => $this->xenditHelper->getPermataVaTitle(),
                    'min_order_amount' => $this->xenditHelper->getPermataVaMinOrderAmount(),
                    'max_order_amount' => $this->xenditHelper->getPermataVaMaxOrderAmount(),
                    'description' => $this->xenditHelper->getPermataVaDescription(),
                    'image' => $this->xenditHelper->getPaymentImage('permatava')
                ],
                'mandiriva' => [
                    'title' => $this->xenditHelper->getMandiriVaTitle(),
                    'min_order_amount' => $this->xenditHelper->getMandiriVaMinOrderAmount(),
                    'max_order_amount' => $this->xenditHelper->getMandiriVaMaxOrderAmount(),
                    'description' => $this->xenditHelper->getMandiriVaDescription(),
                    'image' => $this->xenditHelper->getPaymentImage('mandiriva')
                ],
                'kredivo' => [
                    'title' => $this->xenditHelper->getKredivoTitle(),
                    'min_order_amount' => $this->xenditHelper->getKredivoMinOrderAmount(),
                    'max_order_amount' => $this->xenditHelper->getKredivoMaxOrderAmount(),
                    'description' => $this->xenditHelper->getKredivoDescription(),
                    'image' => $this->xenditHelper->getPaymentImage('kredivo')
                ],
                'cc' => [
                    'title' => $this->xenditHelper->getCcTitle(),
                    'min_order_amount' => $this->xenditHelper->getCcMinOrderAmount(),
                    'max_order_amount' => $this->xenditHelper->getCcMaxOrderAmount(),
                    'description' => $this->xenditHelper->getCcDescription(),
                    'image' => $this->xenditHelper->getCreditCardImages('cc')
                ],
                'cc_subscription' => [
                    'title' => $this->xenditHelper->getCcSubscriptionTitle(),
                    'min_order_amount' => $this->xenditHelper->getCcSubscriptionMinOrderAmount(),
                    'max_order_amount' => $this->xenditHelper->getCcSubscriptionMaxOrderAmount(),
                    'description' => $this->xenditHelper->getCcSubscriptionDescription(),
                    'interval' => $this->xenditHelper->getCcSubscriptionInterval(),
                    'interval_count' => $this->xenditHelper->getCcSubscriptionIntervalCount(),
                    'image' => $this->xenditHelper->getCreditCardImages('cc_subscription')
                ],
                'paymaya' => [
                    'title' => $this->xenditHelper->getPayMayaTitle(),
                    'min_order_amount' => $this->xenditHelper->getPayMayaMinOrderAmount(),
                    'max_order_amount' => $this->xenditHelper->getPayMayaMaxOrderAmount(),
                    'description' => $this->xenditHelper->getPayMayaDescription(),
                    'image' => $this->xenditHelper->getPaymentImage('paymaya')
                ],
                'gcash' => [
                    'title' => $this->xenditHelper->getGCashTitle(),
                    'min_order_amount' => $this->xenditHelper->getGCashMinOrderAmount(),
                    'max_order_amount' => $this->xenditHelper->getGCashMaxOrderAmount(),
                    'description' => $this->xenditHelper->getGCashDescription(),
                    'image' => $this->xenditHelper->getPaymentImage('gcash')
                ],
                'grabpay' => [
                    'title' => $this->xenditHelper->getGrabPayTitle(),
                    'min_order_amount' => $this->xenditHelper->getGrabPayMinOrderAmount(),
                    'max_order_amount' => $this->xenditHelper->getGrabPayMaxOrderAmount(),
                    'description' => $this->xenditHelper->getGrabPayDescription(),
                    'image' => $this->xenditHelper->getPaymentImage('grabpay')
                ],
                'dd_bpi' => [
                    'title' => $this->xenditHelper->getDdBpiTitle(),
                    'min_order_amount' => $this->xenditHelper->getDdBpiMinOrderAmount(),
                    'max_order_amount' => $this->xenditHelper->getDdBpiMaxOrderAmount(),
                    'description' => $this->xenditHelper->getDdBpiDescription(),
                    'image' => $this->xenditHelper->getPaymentImage('dd_bpi')
                ],
                'seven_eleven' => [
                    'title' => $this->xenditHelper->getSevenElevenTitle(),
                    'min_order_amount' => $this->xenditHelper->getSevenElevenMinOrderAmount(),
                    'max_order_amount' => $this->xenditHelper->getSevenElevenMaxOrderAmount(),
                    'description' => $this->xenditHelper->getSevenElevenDescription(),
                    'image' => $this->xenditHelper->getPaymentImage('seven_eleven')
                ],
                'dd_ubp' => [
                    'title' => $this->xenditHelper->getDdUbpTitle(),
                    'min_order_amount' => $this->xenditHelper->getDdUbpMinOrderAmount(),
                    'max_order_amount' => $this->xenditHelper->getDdUbpMaxOrderAmount(),
                    'description' => $this->xenditHelper->getDdUbpDescription(),
                    'image' => $this->xenditHelper->getPaymentImage('dd_ubp')
                ],
                'billease' => [
                    'title' => $this->xenditHelper->getBillEaseTitle(),
                    'min_order_amount' => $this->xenditHelper->getBillEaseMinOrderAmount(),
                    'max_order_amount' => $this->xenditHelper->getBillEaseMaxOrderAmount(),
                    'description' => $this->xenditHelper->getBillEaseDescription(),
                    'image' => $this->xenditHelper->getPaymentImage('billease')
                ],
                // use refactored function
                'cebuana' => [
                    'title' => $this->xenditHelper->getPaymentTitle('cebuana'),
                    'min_order_amount' => $this->xenditHelper->getPaymentMinOrderAmount('cebuana'),
                    'max_order_amount' => $this->xenditHelper->getPaymentMaxOrderAmount('cebuana'),
                    'description' => $this->xenditHelper->getPaymentDescription('cebuana'),
                    'image' => $this->xenditHelper->getPaymentImage('cebuana')
                ],
                'dp_palawan' => [
                    'title' => $this->xenditHelper->getPaymentTitle('dp_palawan'),
                    'min_order_amount' => $this->xenditHelper->getPaymentMinOrderAmount('dp_palawan'),
                    'max_order_amount' => $this->xenditHelper->getPaymentMaxOrderAmount('dp_palawan'),
                    'description' => $this->xenditHelper->getPaymentDescription('dp_palawan'),
                    'image' => $this->xenditHelper->getPaymentImage('dp_palawan')
                ],
                'dp_mlhuillier' => [
                    'title' => $this->xenditHelper->getPaymentTitle('dp_mlhuillier'),
                    'min_order_amount' => $this->xenditHelper->getPaymentMinOrderAmount('dp_mlhuillier'),
                    'max_order_amount' => $this->xenditHelper->getPaymentMaxOrderAmount('dp_mlhuillier'),
                    'description' => $this->xenditHelper->getPaymentDescription('dp_mlhuillier'),
                    'image' => $this->xenditHelper->getPaymentImage('dp_mlhuillier')
                ],
                'dp_ecpay_loan' => [
                    'title' => $this->xenditHelper->getPaymentTitle('dp_ecpay_loan'),
                    'min_order_amount' => $this->xenditHelper->getPaymentMinOrderAmount('dp_ecpay_loan'),
                    'max_order_amount' => $this->xenditHelper->getPaymentMaxOrderAmount('dp_ecpay_loan'),
                    'description' => $this->xenditHelper->getPaymentDescription('dp_ecpay_loan'),
                    'image' => $this->xenditHelper->getPaymentImage('dp_ecpay_loan')
                ],
                'bjbva' => [
                    'title' => $this->xenditHelper->getPaymentTitle('bjbva'),
                    'min_order_amount' => $this->xenditHelper->getPaymentMinOrderAmount('bjbva'),
                    'max_order_amount' => $this->xenditHelper->getPaymentMaxOrderAmount('bjbva'),
                    'description' => $this->xenditHelper->getPaymentDescription('bjbva'),
                    'image' => $this->xenditHelper->getPaymentImage('bjbva')
                ],
                'bsiva' => [
                    'title' => $this->xenditHelper->getPaymentTitle('bsiva'),
                    'min_order_amount' => $this->xenditHelper->getPaymentMinOrderAmount('bsiva'),
                    'max_order_amount' => $this->xenditHelper->getPaymentMaxOrderAmount('bsiva'),
                    'description' => $this->xenditHelper->getPaymentDescription('bsiva'),
                    'image' => $this->xenditHelper->getPaymentImage('bsiva')
                ],
            ]
        ];

        return $config;
    }
}
