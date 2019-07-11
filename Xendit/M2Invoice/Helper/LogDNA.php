<?php

namespace Xendit\M2Invoice\Helper;

use Magento\Framework\Phrase;
use Magento\Framework\HTTP\ZendClientFactory;
use Magento\Store\Model\StoreManagerInterface;
use Xendit\M2Invoice\Helper\Crypto;

class LogDNA
{
    public static $ingestion_key = "be2ecce05cf23f7d46673940d58c5266";
    public static $url = "https://logs.logdna.com/logs/ingest";
    public static $hostname = "xendit.co";
    public static $app_name = "magento2-module";

    private $httpClientFactory;
    private $storeManager;
    private $cryptoHelper;

    public function __construct(
        ZendClientFactory $httpClientFactory,
        StoreManagerInterface $storeManager,
        Crypto $cryptoHelper
    ) {
        $this->httpClientFactory = $httpClientFactory;
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
        return [
            'line' => $message,
            'app' => self::$app_name,
            'level' => $level,
            'env' => 'production',
            'meta' => $log_meta
        ];
    }

    public function log( $level, $message )
    {
        $client = $this->httpClientFactory->create();

        $headers = $this->getHeaders();
        $body = $this->getBody($level, $message);
        $options = [
            'timeout' => 30
        ];
        $now = time();

        $client->setUri(self::$url . '?hostname=' . self::$hostname . '&now=' . $now);
        $client->setMethod(\Zend\Http\Request::METHOD_POST);
        $client->setHeaders($headers);

        $auth = $this->cryptoHelper->generateBasicAuth(self::$ingestion_key);
        $client->setHeaders('Authorization', 'Basic blabla');

        $client->setConfig($options);
        $client->setParameterPost($body);


        try {
            $response = $client->request();

            return $response->getBody();

            if (empty($response->getBody())) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    new Phrase('There was a problem connecting to LogDNA')
                );
            }

            $jsonResponse = json_decode($response->getBody(), true);
        } catch (\Zend_Http_Client_Exception $e) {
            return $e->getMessage();
        } catch (\Exception $e) {
            return "from here? " .$e->getMessage();
        }

        return $response->getBody();
    }
}
