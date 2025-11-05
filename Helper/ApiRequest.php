<?php

namespace Xendit\M2Invoice\Helper;

use Magento\Framework\App\State as AppState;
use Magento\Framework\HTTP\Client\Curl as MagentoCurl;
use Magento\Framework\Phrase;
use Xendit\M2Invoice\Logger\Logger as XenditLogger;
use Xendit\M2Invoice\Model\Payment\Xendit;
use Laminas\Http\Request;

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
     * @var AppState
     */
    private $appState;

    /**
     * ApiRequest constructor.
     * @param Crypto $cryptoHelper
     * @param Xendit $xendit
     * @param XenditLogger $xenditLogger
     * @param MagentoCurl $magentoCurl
     * @param AppState $appState
     */
    public function __construct(
        Crypto $cryptoHelper,
        Xendit $xendit,
        XenditLogger $xenditLogger,
        MagentoCurl $magentoCurl,
        AppState $appState
    ) {
        $this->cryptoHelper = $cryptoHelper;
        $this->xendit = $xendit;
        $this->xenditLogger = $xenditLogger;
        $this->magentoCurl = $magentoCurl;
        $this->appState = $appState;
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
            // Log request details
            $this->xenditLogger->info('API Request', [
                'url' => $url,
                'method' => $method,
                'request_body' => $requestData ? json_encode(value: $requestData) : null,
                'app_state_mode' => $this->appState->getMode()
            ]);

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

            $response = json_decode($this->magentoCurl->getBody(), true);

            // Log response details
            $this->xenditLogger->info('API Response', [
                'url' => $url,
                'status_code' => $this->magentoCurl->getStatus()
            ]);

            return $response;
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

        if (empty($apiKey) || (is_string($apiKey) && empty(trim($apiKey)))) {
            $keyType = $isPublicRequest ? 'Public API Key' : 'Secret API Key';
            throw new \Magento\Framework\Exception\LocalizedException(
                new Phrase(
                    'Xendit %1 is not configured. Please refer to the user guide to configure your API keys in the admin panel.',
                    [$keyType]
                )
            );
        }

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
