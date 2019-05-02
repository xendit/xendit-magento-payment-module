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

    public function getValidationKey()
    {
        if ($this->isLive()) {
            return $this->getConfigData('validation_key');
        } else {
            return $this->getConfigData('test_validation_key');
        }
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

    public function getBusinessEmail()
    {
        return $this->getConfigData('business_email');
    }

    public function getUrl()
    {
        return $this->getConfigData('xendit_url');
    }
}