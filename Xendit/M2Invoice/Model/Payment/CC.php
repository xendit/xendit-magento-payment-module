<?php

namespace Xendit\M2Invoice\Model\Payment;

// use Magento\Payment\Model\CcGenericConfigProvider;
use Zend\Http\Client;
use Xendit\M2Invoice\Model\Payment\M2Invoice;
use Xendit\M2Invoice\Helper\Crypto;
use Xendit\M2Invoice\Helper\Data;
use Magento\Framework\Model\Context;

class CC extends \Magento\Payment\Model\Method\Cc
{
    const CODE = 'cc';
    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = self::CODE;

    protected $_isGateway = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;

    protected $zendClient;
    protected $m2Invoice;
    protected $cryptoHelper;
    protected $dataHelper;

    public function __construct(
        Client $zendClient,
        M2Invoice $m2Invoice,
        Crypto $cryptoHelper,
        Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = [],
        Data $dataHelper
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $moduleList,
            $localeDate,
            null,
            null,
            $data
        );

        $this->zendClient = $zendClient;
        $this->m2Invoice = $m2Invoice;
        $this->cryptoHelper = $cryptoHelper;
        $this->dataHelper = $dataHelper;
    }

    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        //todo add functionality later
    }
 
    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $order = $payment->getOrder();
        $orderId = $order->getRealOrderId();
        $additionalData = $this->getAdditionalData();

        try {
            $apiKey = $this->m2Invoice->getApiKey();
            $apiUrl = $this->m2Invoice->getUrl();
            $requestData = [
                'token_id' => $additionalData['token_id'],
                'amount' => $amount,
                'external_id' => $this->dataHelper->getExternalId($orderId),
                'card_cvn' => $additionalData['cc_cc_cid']
            ];

            $this->zendClient->reset();
            $this->zendClient->setUri($apiUrl . '/payment/xendit/credit-card/charges');
            $this->zendClient->setMethod(\Zend\Http\Request::METHOD_POST);
            $this->zendClient->setHeaders([
                'Authorization' => $this->cryptoHelper->generateBasicAuth($apiKey),
                'x-plugin-name' => 'MAGENTO2',
                'x-plugin-method' => 'CREDIT_CARD',
            ]);
            $this->zendClient->setParameterPost($requestData);

            $adapter = new \Zend\Http\Client\Adapter\Curl();
            $curlOptions = array(
                CURLOPT_USERAGENT      => 'Zend_Curl_Adapter',
                CURLOPT_HEADER         => 0,
                CURLOPT_VERBOSE        => 0,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            );
            $adapter->setOptions(array('curloptions' => $curlOptions));
            $this->zendClient->setAdapter($adapter);

            $this->zendClient->send();

            $response = $this->zendClient->getResponse();

            return $this;
        } catch (Exception $e) {
            echo $e->getMessage();

            return false;
        }
    }

    private function log($param, $message)
    {
        try {
            $this->_logger->log(100, $message . var_export($param, true));
        } catch (\Exception $e) {
            $this->_logger->log(100, $message . var_export($param, true));
        }
    }
}