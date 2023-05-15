<?php
namespace Xendit\M2Invoice\Logger;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Logger\Handler\Base;
use Magento\Store\Model\ScopeInterface;
use Monolog\Logger;

/**
 * Class Handler
 * @package Xendit\M2Invoice\Logger
 */
class Handler extends Base
{
    protected $loggerType = Logger::INFO;
    protected $fileName = '/var/log/xendit_payment.log';

    /** @var ScopeConfigInterface $scopeConfig */
    protected $scopeConfig;

    /**
     * @param DriverInterface $filesystem
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        DriverInterface $filesystem,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
        parent::__construct($filesystem, null, null);
    }

    /**
     * @param array $record
     * @return bool
     */
    public function isHandling(array $record): bool
    {
        $isDebugEnabled = (bool)$this->scopeConfig->getValue("payment/xendit/debug", ScopeInterface::SCOPE_STORE);
        if ($isDebugEnabled) {
            return true;
        }

        return $record['level'] >= \Monolog\Logger::WARNING;
    }
}
