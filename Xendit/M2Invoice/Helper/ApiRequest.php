<?php

namespace Xendit\M2Invoice\Helper;

use Magento\Framework\HTTP\ZendClientFactory;
use Psr\Log\LoggerInterface;
use Xendit\M2Invoice\Model\Payment\M2Invoice;

class ApiRequest
{
    private $_httpClientFactory;

    private $_cryptoHelper;

    private $_m2Invoice;

    private $_logger;

    public function __construct(
        ZendClientFactory $httpClientFactory,
        Crypto $cryptoHelper,
        M2Invoice $m2Invoice,
        LoggerInterface $logger
    ) {
        $this->_httpClientFactory = $httpClientFactory;
        $this->_cryptoHelper = $cryptoHelper;
        $this->_m2Invoice = $m2Invoice;
        $this->_logger = $logger;
    }

    public function request($url, $method, $requestData = null, $isPublicRequest = false, $preferredMethod = null)
    {
        $client = $this->_httpClientFactory->create();
        $headers = $this->getHeaders($isPublicRequest, $preferredMethod);

        $client->setUri($url);
        $client->setMethod($method);
        $client->setHeaders($headers);

        if ($requestData != null) {
            $client->setParameterPost($requestData);
        }

        $log = array(
            'uri' => $url,
            'method' => $method
        );

        try {
            $response = $client->request();

            if (empty($response->getBody())) {
                throw new \Exception(
                    'There was a problem connecting to Xendit'
                );
            }

            $jsonResponse = json_decode($response->getBody(), true);
            $log['response'] = $jsonResponse;
        } catch (\Zend_Http_Client_Exception $e) {
            throw new \Magento\Payment\Gateway\Http\ClientException(__($e->getMessage()));
        } catch (\Exception $e) {
            throw $e;
        } finally {
            $this->_logger->debug('Xendit API Request', $log);
        }

        return $jsonResponse;
    }

    private function getHeaders($isPublicRequest, $preferredMethod = null)
    {
        $apiKey = $isPublicRequest ? $this->_m2Invoice->getPublicApiKey() : $this->_m2Invoice->getApiKey();
        $auth = $this->_cryptoHelper->generateBasicAuth($apiKey);

        $headers = array(
            'Authorization' => $auth,
            'Content-Type' => 'application/json',
            'x-plugin-name' => 'MAGENTO2',
            'user-agent' => 'Magento 2 Module'
        );

        if ($preferredMethod !== null) {
            array_push(
                $headers,
                array('x-plugin-method' => $preferredMethod)
            );
        }

        return $headers;
    }
}