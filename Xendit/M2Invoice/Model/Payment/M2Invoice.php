<?php

namespace Xendit\M2Invoice\Model\Payment;

class M2Invoice extends \Magento\Payment\Model\Method\AbstractMethod
{
    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'm2invoice';

    public function getApiKey()
    {
        if ($this->isLive()) {
            return $this->getConfigData('private_key');
        } else {
            return $this->getConfigData('test_private_key');
        }
    }

    public function getPublicApiKey()
    {
        if ($this->isLive()) {
            return $this->getConfigData('public_key');
        } else {
            return $this->getConfigData('test_public_key');
        }
    }

    public function getSubscriptionInterval() {
        return $this->getConfigData('card_subscription_interval');
    }

    public function getSubscriptionIntervalCount() {
        return $this->getConfigData('card_subscription_interval_count');
    }

    public function getSubscriptionDescription() {
        return $this->getConfigData('card_subscription_card_subscription_description');
    }

    public function isLive()
    {
        $xenditEnv = $this->getConfigData('xendit_env');

        if ('live' == $xenditEnv) {
            return true;
        }

        return false;
    }

    public function getEnvironment()
    {
        return $this->getConfigData('xendit_env');
    }

    public function getUrl()
    {
        return $this->getConfigData('xendit_url');
    }

    public function getCardPaymentType()
    {
        return $this->getConfigData('card_payment_type');
    }

    public function getAllowedMethod()
    {
        return $this->getConfigData('allowed_method');
    }

    public function getChosenMethods()
    {
        return $this->getConfigData('chosen_methods');
    }

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
