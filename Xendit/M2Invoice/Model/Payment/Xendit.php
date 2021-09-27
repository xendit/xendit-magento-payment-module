<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Payment\Model\Method\AbstractMethod;

/**
 * Class Xendit
 * @package Xendit\M2Invoice\Model\Payment
 */
class Xendit extends AbstractMethod
{
    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'xendit';

    /**
     * @return mixed
     */
    public function getApiKey()
    {
        if ($this->isLive()) {
            return $this->getConfigData('private_key');
        } else {
            return $this->getConfigData('test_private_key');
        }
    }

    /**
     * @return mixed
     */
    public function getPublicApiKey()
    {
        if ($this->isLive()) {
            return $this->getConfigData('public_key');
        } else {
            return $this->getConfigData('test_public_key');
        }
    }

    /**
     * @return bool
     */
    public function isLive()
    {
        $xenditEnv = $this->getConfigData('xendit_env');

        if ('live' == $xenditEnv) {
            return true;
        }

        return false;
    }

    /**
     * @return mixed
     */
    public function getEnvironment()
    {
        return $this->getConfigData('xendit_env');
    }

    /**
     * @return mixed
     */
    public function getUrl()
    {
        return $this->getConfigData('xendit_url');
    }

    /**
     * @return mixed
     */
    public function getUiUrl()
    {
        return $this->getConfigData('ui_url');
    }

    /**
     * @return mixed
     */
    public function getCardPaymentType()
    {
        return $this->getConfigData('card_payment_type');
    }

    /**
     * @return mixed
     */
    public function getAllowedMethod()
    {
        return $this->getConfigData('allowed_method');
    }

    /**
     * @return mixed
     */
    public function getChosenMethods()
    {
        return $this->getConfigData('chosen_methods');
    }

    /**
     * @return mixed
     */
    public function getIsActive()
    {
        return $this->getConfigData('active');
    }

    /**
     * @return array
     */
    public function getEnabledPromo()
    {
        $promo = [];
        $bankCodes = [
            'bca',
            'bni',
            'bri',
            'cimb',
            'citibank',
            'danamon',
            'dbs',
            'hsbc',
            'mandiri',
            'maybank',
            'mega',
            'mnc',
            'permata',
            'sc',
            'uob',
        ];

        foreach ($bankCodes as $bankCode) {
            if ($this->getConfigData('card_promo_' . $bankCode . '_active')) {
                $binListCandidate = explode(',', $this->getConfigData('card_promo_' . $bankCode . '_bin_list'));
                $binList = $result = array_filter(
                    $binListCandidate,
                    function ($value) {
                        return strlen($value) === 6;
                    }
                );
                $promo[] = [
                    'rule_id' => $this->getConfigData('card_promo_' . $bankCode . '_rule'),
                    'bin_list' => $binList
                ];
            }
        }

        return $promo;
    }
}
