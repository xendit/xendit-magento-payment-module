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
                    'title' => $this->xenditHelper->getPaymentTitle("dana"),
                    'min_order_amount' => $this->xenditHelper->getPaymentMinOrderAmount("dana"),
                    'max_order_amount' => $this->xenditHelper->getPaymentMaxOrderAmount("dana"),
                    'description' => $this->xenditHelper->getPaymentDescription("dana"),
                    'image' => $this->xenditHelper->getPaymentImage('dana')
                ],
                'ovo' => [
                    'title' => $this->xenditHelper->getPaymentTitle("ovo"),
                    'min_order_amount' => $this->xenditHelper->getPaymentMinOrderAmount("ovo"),
                    'max_order_amount' => $this->xenditHelper->getPaymentMaxOrderAmount("ovo"),
                    'description' => $this->xenditHelper->getPaymentDescription("ovo"),
                    'image' => $this->xenditHelper->getPaymentImage('ovo')
                ],
                'shopeepay' => [
                    'title' => $this->xenditHelper->getPaymentTitle("shopeepay"),
                    'min_order_amount' => $this->xenditHelper->getPaymentMinOrderAmount("shopeepay"),
                    'max_order_amount' => $this->xenditHelper->getPaymentMaxOrderAmount("shopeepay"),
                    'description' => $this->xenditHelper->getPaymentDescription("shopeepay"),
                    'image' => $this->xenditHelper->getPaymentImage('shopeepay')
                ],
                'linkaja' => [
                    'title' => $this->xenditHelper->getPaymentTitle("linkaja"),
                    'min_order_amount' => $this->xenditHelper->getPaymentMinOrderAmount("linkaja"),
                    'max_order_amount' => $this->xenditHelper->getPaymentMaxOrderAmount("linkaja"),
                    'description' => $this->xenditHelper->getPaymentDescription("linkaja"),
                    'image' => $this->xenditHelper->getPaymentImage('linkaja')
                ],
                'alfamart' => [
                    'title' => $this->xenditHelper->getPaymentTitle("alfamart"),
                    'min_order_amount' => $this->xenditHelper->getPaymentMinOrderAmount("alfamart"),
                    'max_order_amount' => $this->xenditHelper->getPaymentMaxOrderAmount("alfamart"),
                    'description' => $this->xenditHelper->getPaymentDescription("alfamart"),
                    'image' => $this->xenditHelper->getPaymentImage('alfamart')
                ],
                'indomaret' => [
                    'title' => $this->xenditHelper->getPaymentTitle("indomaret"),
                    'min_order_amount' => $this->xenditHelper->getPaymentMinOrderAmount("indomaret"),
                    'max_order_amount' => $this->xenditHelper->getPaymentMaxOrderAmount("indomaret"),
                    'description' => $this->xenditHelper->getPaymentDescription("indomaret"),
                    'image' => $this->xenditHelper->getPaymentImage('indomaret')
                ],
                'bcava' => [
                    'title' => $this->xenditHelper->getPaymentTitle("bcava"),
                    'min_order_amount' => $this->xenditHelper->getPaymentMinOrderAmount("bcava"),
                    'max_order_amount' => $this->xenditHelper->getPaymentMaxOrderAmount("bcava"),
                    'description' => $this->xenditHelper->getPaymentDescription("bcava"),
                    'image' => $this->xenditHelper->getPaymentImage('bcava')
                ],
                'briva' => [
                    'title' => $this->xenditHelper->getPaymentTitle("briva"),
                    'min_order_amount' => $this->xenditHelper->getPaymentMinOrderAmount("briva"),
                    'max_order_amount' => $this->xenditHelper->getPaymentMaxOrderAmount("briva"),
                    'description' => $this->xenditHelper->getPaymentDescription("briva"),
                    'image' => $this->xenditHelper->getPaymentImage('briva')
                ],
                'bniva' => [
                    'title' => $this->xenditHelper->getPaymentTitle("bniva"),
                    'min_order_amount' => $this->xenditHelper->getPaymentMinOrderAmount("bniva"),
                    'max_order_amount' => $this->xenditHelper->getPaymentMaxOrderAmount("bniva"),
                    'description' => $this->xenditHelper->getPaymentDescription("bniva"),
                    'image' => $this->xenditHelper->getPaymentImage('bniva')
                ],
                'qris' => [
                    'title' => $this->xenditHelper->getPaymentTitle("qris"),
                    'min_order_amount' => $this->xenditHelper->getPaymentMinOrderAmount("qris"),
                    'max_order_amount' => $this->xenditHelper->getPaymentMaxOrderAmount("qris"),
                    'description' => $this->xenditHelper->getPaymentDescription("qris"),
                    'image' => $this->xenditHelper->getPaymentImage('qris')
                ],
                'dd_bri' => [
                    'title' => $this->xenditHelper->getPaymentTitle("dd_bri"),
                    'min_order_amount' => $this->xenditHelper->getPaymentMinOrderAmount("dd_bri"),
                    'max_order_amount' => $this->xenditHelper->getPaymentMaxOrderAmount("dd_bri"),
                    'description' => $this->xenditHelper->getPaymentDescription("dd_bri"),
                    'image' => $this->xenditHelper->getPaymentImage('dd_bri')
                ],
                'permatava' => [
                    'title' => $this->xenditHelper->getPaymentTitle("permatava"),
                    'min_order_amount' => $this->xenditHelper->getPaymentMinOrderAmount("permatava"),
                    'max_order_amount' => $this->xenditHelper->getPaymentMaxOrderAmount("permatava"),
                    'description' => $this->xenditHelper->getPaymentDescription("permatava"),
                    'image' => $this->xenditHelper->getPaymentImage('permatava')
                ],
                'mandiriva' => [
                    'title' => $this->xenditHelper->getPaymentTitle("mandiriva"),
                    'min_order_amount' => $this->xenditHelper->getPaymentMinOrderAmount("mandiriva"),
                    'max_order_amount' => $this->xenditHelper->getPaymentMaxOrderAmount("mandiriva"),
                    'description' => $this->xenditHelper->getPaymentDescription("mandiriva"),
                    'image' => $this->xenditHelper->getPaymentImage('mandiriva')
                ],
                'kredivo' => [
                    'title' => $this->xenditHelper->getPaymentTitle("kredivo"),
                    'min_order_amount' => $this->xenditHelper->getPaymentMinOrderAmount("kredivo"),
                    'max_order_amount' => $this->xenditHelper->getPaymentMaxOrderAmount("kredivo"),
                    'description' => $this->xenditHelper->getPaymentDescription("kredivo"),
                    'image' => $this->xenditHelper->getPaymentImage('kredivo')
                ],
                'cc' => [
                    'title' => $this->xenditHelper->getPaymentTitle("cc"),
                    'min_order_amount' => $this->xenditHelper->getPaymentMinOrderAmount("cc"),
                    'max_order_amount' => $this->xenditHelper->getPaymentMaxOrderAmount("cc"),
                    'description' => $this->xenditHelper->getPaymentDescription("cc"),
                    'image' => $this->xenditHelper->getCreditCardImages('cc')
                ],
                'paymaya' => [
                    'title' => $this->xenditHelper->getPaymentTitle("paymaya"),
                    'min_order_amount' => $this->xenditHelper->getPaymentMinOrderAmount("paymaya"),
                    'max_order_amount' => $this->xenditHelper->getPaymentMaxOrderAmount("paymaya"),
                    'description' => $this->xenditHelper->getPaymentDescription("paymaya"),
                    'image' => $this->xenditHelper->getPaymentImage('paymaya')
                ],
                'gcash' => [
                    'title' => $this->xenditHelper->getPaymentTitle("gcash"),
                    'min_order_amount' => $this->xenditHelper->getPaymentMinOrderAmount("gcash"),
                    'max_order_amount' => $this->xenditHelper->getPaymentMaxOrderAmount("gcash"),
                    'description' => $this->xenditHelper->getPaymentDescription("gcash"),
                    'image' => $this->xenditHelper->getPaymentImage('gcash')
                ],
                'grabpay' => [
                    'title' => $this->xenditHelper->getPaymentTitle("grabpay"),
                    'min_order_amount' => $this->xenditHelper->getPaymentMinOrderAmount("grabpay"),
                    'max_order_amount' => $this->xenditHelper->getPaymentMaxOrderAmount("grabpay"),
                    'description' => $this->xenditHelper->getPaymentDescription("grabpay"),
                    'image' => $this->xenditHelper->getPaymentImage('grabpay')
                ],
                'dd_bpi' => [
                    'title' => $this->xenditHelper->getPaymentTitle("dd_bpi"),
                    'min_order_amount' => $this->xenditHelper->getPaymentMinOrderAmount("dd_bpi"),
                    'max_order_amount' => $this->xenditHelper->getPaymentMaxOrderAmount("dd_bpi"),
                    'description' => $this->xenditHelper->getPaymentDescription("dd_bpi"),
                    'image' => $this->xenditHelper->getPaymentImage('dd_bpi')
                ],
                'seven_eleven' => [
                    'title' => $this->xenditHelper->getPaymentTitle("seven_eleven"),
                    'min_order_amount' => $this->xenditHelper->getPaymentMinOrderAmount("seven_eleven"),
                    'max_order_amount' => $this->xenditHelper->getPaymentMaxOrderAmount("seven_eleven"),
                    'description' => $this->xenditHelper->getPaymentDescription("seven_eleven"),
                    'image' => $this->xenditHelper->getPaymentImage('seven_eleven')
                ],
                'dd_ubp' => [
                    'title' => $this->xenditHelper->getPaymentTitle("dd_ubp"),
                    'min_order_amount' => $this->xenditHelper->getPaymentMinOrderAmount("dd_ubp"),
                    'max_order_amount' => $this->xenditHelper->getPaymentMaxOrderAmount("dd_ubp"),
                    'description' => $this->xenditHelper->getPaymentDescription("dd_ubp"),
                    'image' => $this->xenditHelper->getPaymentImage('dd_ubp')
                ],
                'billease' => [
                    'title' => $this->xenditHelper->getPaymentTitle("billease"),
                    'min_order_amount' => $this->xenditHelper->getPaymentMinOrderAmount("billease"),
                    'max_order_amount' => $this->xenditHelper->getPaymentMaxOrderAmount("billease"),
                    'description' => $this->xenditHelper->getPaymentDescription("billease"),
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
                'cashalo' => [
                    'title' => $this->xenditHelper->getPaymentTitle("cashalo"),
                    'min_order_amount' => $this->xenditHelper->getPaymentMinOrderAmount("cashalo"),
                    'max_order_amount' => $this->xenditHelper->getPaymentMaxOrderAmount("cashalo"),
                    'description' => $this->xenditHelper->getPaymentDescription("cashalo"),
                    'image' => $this->xenditHelper->getPaymentImage('cashalo')
                ],
                'shopeepayph' => [
                    'title' => $this->xenditHelper->getPaymentTitle("shopeepayph"),
                    'min_order_amount' => $this->xenditHelper->getPaymentMinOrderAmount("shopeepayph"),
                    'max_order_amount' => $this->xenditHelper->getPaymentMaxOrderAmount("shopeepayph"),
                    'description' => $this->xenditHelper->getPaymentDescription("shopeepayph"),
                    'image' => $this->xenditHelper->getPaymentImage('shopeepay')
                ],
                'uangme' => [
                    'title' => $this->xenditHelper->getPaymentTitle("uangme"),
                    'min_order_amount' => $this->xenditHelper->getPaymentMinOrderAmount("uangme"),
                    'max_order_amount' => $this->xenditHelper->getPaymentMaxOrderAmount("uangme"),
                    'description' => $this->xenditHelper->getPaymentDescription("uangme"),
                    'image' => $this->xenditHelper->getPaymentImage('uangme')
                ],
                'astrapay' => [
                    'title' => $this->xenditHelper->getPaymentTitle("astrapay"),
                    'min_order_amount' => $this->xenditHelper->getPaymentMinOrderAmount("astrapay"),
                    'max_order_amount' => $this->xenditHelper->getPaymentMaxOrderAmount("astrapay"),
                    'description' => $this->xenditHelper->getPaymentDescription("astrapay"),
                    'image' => $this->xenditHelper->getPaymentImage('astrapay')
                ],
                'akulaku' => [
                    'title' => $this->xenditHelper->getPaymentTitle("akulaku"),
                    'min_order_amount' => $this->xenditHelper->getPaymentMinOrderAmount("akulaku"),
                    'max_order_amount' => $this->xenditHelper->getPaymentMaxOrderAmount("akulaku"),
                    'description' => $this->xenditHelper->getPaymentDescription("akulaku"),
                    'image' => $this->xenditHelper->getPaymentImage('akulaku')
                ],
                'dd_rcbc' => [
                    'title' => $this->xenditHelper->getPaymentTitle("dd_rcbc"),
                    'min_order_amount' => $this->xenditHelper->getPaymentMinOrderAmount("dd_rcbc"),
                    'max_order_amount' => $this->xenditHelper->getPaymentMaxOrderAmount("dd_rcbc"),
                    'description' => $this->xenditHelper->getPaymentDescription("dd_rcbc"),
                    'image' => $this->xenditHelper->getPaymentImage('dd_rcbc')
                ],
            ]
        ];

        return $config;
    }
}
