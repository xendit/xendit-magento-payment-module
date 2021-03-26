<?php

namespace Xendit\M2Invoice\Helper;

use Magento\Framework\HTTP\Client\Curl;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class LogDNA
 * @package Xendit\M2Invoice\Helper
 */
class LogDNA
{
    public static $ingestion_key = "be2ecce05cf23f7d46673940d58c5266";
    public static $url = "https://logs.logdna.com/logs/ingest";
    public static $hostname = "xendit.co";
    public static $app_name = "magento2-module";

    /**
     * @var Curl
     */
    private $curlClient;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Crypto
     */
    private $cryptoHelper;

    /**
     * LogDNA constructor.
     * @param Curl $httpClientFactory
     * @param StoreManagerInterface $storeManager
     * @param Crypto $cryptoHelper
     */
    public function __construct(
        Curl $httpClientFactory,
        StoreManagerInterface $storeManager,
        Crypto $cryptoHelper
    ) {
        $this->curlClient = $httpClientFactory;
        $this->storeManager = $storeManager;
        $this->cryptoHelper = $cryptoHelper;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        $headers = [
            'Content-Type' => 'application/json',
            'x-plugin-name' => 'MAGENTO2',
            'user-agent' => 'Magento 2 Module'
        ];

        return $headers;
    }

    /**
     * @param $level
     * @param $message
     * @param $context
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getBody( $level, $message, $context )
    {
        $default_log_meta = [
            'store_name' => $this->storeManager->getStore()->getName()
        ];
        $log_meta = $default_log_meta;

        if ($context !== null) {
            $log_meta = array_merge($default_log_meta, $context);
        }

        $content = array([
            'line' => $message,
            'app' => self::$app_name,
            'level' => $level,
            'env' => 'production',
            'meta' => $log_meta
        ]);
        return [
            'lines' => $content
        ];
    }

    /**
     * @param $level
     * @param $message
     * @param null $context
     * @return string|void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function log( $level, $message, $context = null )
    {
        $headers = $this->getHeaders();
        $body = $this->getBody($level, $message, $context );
        $now = time();
        $url = self::$url . '?hostname=' . self::$hostname . '&now=' . $now;

        try {
            $this->curlClient->setHeaders($headers);
            $this->curlClient->setCredentials(self::$ingestion_key, '');
            $this->curlClient->post($url, json_encode($body));
            $response = $this->curlClient->getBody();

            return $response;
        } catch (\Zend_Http_Client_Exception $e) {
            return $e->getMessage();
        } catch (\Exception $e) {
            return;
        }
    }
}
