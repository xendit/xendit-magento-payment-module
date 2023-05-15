<?php

namespace Xendit\M2Invoice\Helper;

use Magento\Framework\HTTP\Client\Curl as MagentoCurl;
use Magento\Framework\Phrase;
use Xendit\M2Invoice\Logger\Logger as XenditLogger;
use Xendit\M2Invoice\Model\Payment\Xendit;
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

    /**
     * @param $url
     * @param $method
     * @param $requestData
     * @param $isPublicRequest
     * @param $preferredMethod
     * @param $customOptions
     * @param $customHeaders
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function request(
        $url,
        $method,
        $requestData = null,
        $isPublicRequest = false,
        $preferredMethod = null,
        $customOptions = [],
        $customHeaders = []
    ) {
        try {
            $headers = $this->getHeaders($isPublicRequest, $preferredMethod, $customHeaders);
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

            return json_decode($this->magentoCurl->getBody(), true);
        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(
                new Phrase($e->getMessage())
            );
        }
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
            'x-plugin-version' => \Xendit\M2Invoice\Helper\Data::XENDIT_M2INVOICE_VERSION
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
