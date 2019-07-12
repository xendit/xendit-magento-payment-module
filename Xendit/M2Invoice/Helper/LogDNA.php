<?php

namespace Xendit\M2Invoice\Helper;

use Magento\Framework\Phrase;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Store\Model\StoreManagerInterface;

class LogDNA
{
    public static $ingestion_key = "be2ecce05cf23f7d46673940d58c5266";
    public static $url = "https://logs.logdna.com/logs/ingest";
    public static $hostname = "xendit.co";
    public static $app_name = "magento2-module";

    private $curlClient;
    private $storeManager;
    private $cryptoHelper;

    public function __construct(
        Curl $httpClientFactory,
        StoreManagerInterface $storeManager,
        Crypto $cryptoHelper
    ) {
        $this->curlClient = $httpClientFactory;
        $this->storeManager = $storeManager;
        $this->cryptoHelper = $cryptoHelper;
    }

    public function getHeaders()
    {
        $headers = [
            'Content-Type' => 'application/json',
            'x-plugin-name' => 'MAGENTO2',
            'user-agent' => 'Magento 2 Module'
        ];

        return $headers;
    }

    public function getBody( $level, $message )
    {
        $log_meta = array(
            'store_name' => $this->storeManager->getStore()->getName()
        );
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

    public function log( $level, $message )
    {
        $headers = $this->getHeaders();
        $body = $this->getBody($level, $message);
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
