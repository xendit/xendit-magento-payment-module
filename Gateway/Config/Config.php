<?php

namespace Xendit\M2Invoice\Gateway\Config;

class Config extends \Magento\Payment\Gateway\Config\Config
{
    const LOG_FILE = 'xendit.log';
    const CODE = 'm2invoice';

    const KEY_XENDIT_ENV = 'xendit_env';
    const KEY_XENDIT_URL = 'xendit_url';
    const KEY_DESCRIPTION = 'description';
    const KEY_TEST_MODE_DESCRIPTION = 'test_description';

    protected $_log;

    public function __construct(
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->_log = $logger;
    }

    public function getEnvironment()
    {
        return $this->getValue(self::KEY_XENDIT_ENV);
    }

    public function getUrl()
    {
        return $this->getValue(self::KEY_XENDIT_URL);
    }

    public function getDescription()
    {
        $this->_log->info('------------------Testing-------------------' . print_r( self::KEY_DESCRIPTION, true ) );
        return $this->getValue(self::KEY_DESCRIPTION);
    }

    public function getTestDescription()
    {
        return $this->getValue(self::KEY_TEST_MODE_DESCRIPTION);
    }
}