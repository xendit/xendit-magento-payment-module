<?php

namespace Xendit\M2Invoice\Helper;

use Magento\Framework\Phrase;
use Magento\Framework\HTTP\ZendClientFactory;
use Psr\Log\LoggerInterface;
use Xendit\M2Invoice\Model\Payment\M2Invoice;

class ApiRequest
{
    private $httpClientFactory;

    private $cryptoHelper;

    private $m2Invoice;

    private $logger;

    public function __construct(
        ZendClientFactory $httpClientFactory,
        Crypto $cryptoHelper,
        M2Invoice $m2Invoice,
        LoggerInterface $logger
    ) {
        $this->httpClientFactory = $httpClientFactory;
        $this->cryptoHelper = $cryptoHelper;
        $this->m2Invoice = $m2Invoice;
        $this->logger = $logger;
    }

    public function request($url, $method, $requestData = null, $isPublicRequest = false, $preferredMethod = null, $customOptions = [])
    {
        $client = $this->httpClientFactory->create();
        $headers = $this->getHeaders($isPublicRequest, $preferredMethod);
        
        $options = [
            'timeout' => 30
        ];

        $client->setUri($url);
        $client->setMethod($method);
        $client->setHeaders($headers);
        $client->setConfig(array_merge($options, $customOptions));

        if ($requestData != null) {
            $client->setParameterPost($requestData);
        }

        try {
            $response = $client->request();

            if (empty($response->getBody())) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    new Phrase('There was a problem connecting to Xendit')
                );
            }

            $jsonResponse = json_decode($response->getBody(), true);
        } catch (\Zend_Http_Client_Exception $e) {
            throw new \Magento\Payment\Gateway\Http\ClientException(__($e->getMessage()));
        } catch (\Exception $e) {
            throw $e;
        }

        return $jsonResponse;
    }

    private function getHeaders($isPublicRequest, $preferredMethod = null)
    {
        $apiKey = $isPublicRequest ? $this->m2Invoice->getPublicApiKey() : $this->m2Invoice->getApiKey();
        $auth = $this->cryptoHelper->generateBasicAuth($apiKey);

        $headers = [
            'Authorization' => $auth,
            'Content-Type' => 'application/json',
            'x-plugin-name' => 'MAGENTO2',
            'user-agent' => 'Magento 2 Module'
        ];

        if ($preferredMethod !== null) {
            array_merge(
                $headers,
                [ 'x-plugin-method' => $preferredMethod ]
            );
        }

        return $headers;
    }
}
