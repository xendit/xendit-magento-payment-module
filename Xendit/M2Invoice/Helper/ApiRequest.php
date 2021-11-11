<?php

namespace Xendit\M2Invoice\Helper;

use Magento\Framework\Phrase;
use Xendit\M2Invoice\Logger\Logger as XenditLogger;
use Xendit\M2Invoice\Model\Payment\Xendit;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Framework\HTTP\Client\Curl as MagentoCurl;
use Zend\Http\Request;

/**
 * Class ApiRequest
 * @package Xendit\M2Invoice\Helper
 */
class ApiRequest
{
    /**
     * @var Crypto
     */
    private $cryptoHelper;

    /**
     * @var Xendit
     */
    private $xendit;

    /**
     * @var XenditLogger
     */
    private $xenditLogger;

    /**
     * @var MagentoCurl
     */
    private $magentoCurl;

    /**
     * ApiRequest constructor.
     * @param Crypto $cryptoHelper
     * @param Xendit $xendit
     * @param XenditLogger $xenditLogger
     * @param MagentoCurl $magentoCurl
     */
    public function __construct(
        Crypto $cryptoHelper,
        Xendit $xendit,
        XenditLogger $xenditLogger,
        MagentoCurl $magentoCurl
    ) {
        $this->cryptoHelper = $cryptoHelper;
        $this->xendit = $xendit;
        $this->xenditLogger = $xenditLogger;
        $this->magentoCurl = $magentoCurl;
    }

    public function request(
        $url,
        $method,
        $requestData = null,
        $isPublicRequest = false,
        $preferredMethod = null,
        $customOptions = [],
        $customHeaders = []
    ) {
        $headers = $this->getHeaders($isPublicRequest, $preferredMethod, $customHeaders);

        try {
            $this->magentoCurl->setHeaders($headers);

            $this->magentoCurl->setTimeout(30);
            
            if ($method == Request::METHOD_GET) {
                // GET request
                $this->magentoCurl->get($url);
            } else {
                // POST request
                $this->magentoCurl->post($url, json_encode($requestData));
            }

            if (empty($this->magentoCurl->getBody())) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    new Phrase('There was a problem connecting to Xendit')
                );
            }

            $jsonResponse = json_decode($this->magentoCurl->getBody(), true);
        } catch (\Zend_Http_Client_Exception $e) {
            throw new ClientException(__($e->getMessage()));
        } catch (\Exception $e) {
            throw $e;
        }

        return $jsonResponse;
    }

    /**
     * @param $isPublicRequest
     * @param null $preferredMethod
     * @param array $customHeaders
     * @return array
     */
    private function getHeaders($isPublicRequest, $preferredMethod = null, $customHeaders = [])
    {
        $apiKey = $isPublicRequest ? $this->xendit->getPublicApiKey() : $this->xendit->getApiKey();
        $auth = $this->cryptoHelper->generateBasicAuth($apiKey);

        $headers = [
            'Authorization' => $auth,
            'Content-Type' => 'application/json',
            'x-plugin-name' => 'MAGENTO2',
            'user-agent' => 'Magento 2 Module',
            'x-plugin-version' => '3.2.1'
        ];

        if ($preferredMethod !== null) {
            $headers['x-plugin-method'] = $preferredMethod;
        }

        if (!empty($customHeaders)) {
            foreach ($customHeaders as $customHeader => $value) {
                $headers[$customHeader] = $value;
            }
        }

        return $headers;
    }
}
