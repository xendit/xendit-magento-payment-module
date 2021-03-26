<?php

namespace Xendit\M2Invoice\Gateway\Config;

use Magento\Payment\Gateway\Config\Config as MagentoPaymentGatewayConfig;

/**
 * Class Config
 * @package Xendit\M2Invoice\Gateway\Config
 */
class Config extends MagentoPaymentGatewayConfig
{
    const LOG_FILE = 'xendit.log';
    const CODE = 'xendit';

    const KEY_XENDIT_ENV = 'xendit_env';
    const KEY_XENDIT_URL = 'xendit_url';
    const KEY_DESCRIPTION = 'description';
    const KEY_TEST_MODE_DESCRIPTION = 'test_description';

    /**
     * @return mixed
     */
    public function getEnvironment()
    {
        return $this->getValue(self::KEY_XENDIT_ENV);
    }

    /**
     * @return mixed
     */
    public function getUrl()
    {
        return $this->getValue(self::KEY_XENDIT_URL);
    }

    /**
     * @return mixed
     */
    public function getDescription()
    {
        return $this->getValue(self::KEY_DESCRIPTION);
    }

    /**
     * @return mixed
     */
    public function getTestDescription()
    {
        return $this->getValue(self::KEY_TEST_MODE_DESCRIPTION);
    }
}
