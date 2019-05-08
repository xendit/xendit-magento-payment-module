<?php

namespace Xendit\M2Invoice\Model\Payment;

use Xendit\M2Invoice\Helper\Crypto;
use Xendit\M2Invoice\Helper\Data;
use Magento\Framework\Model\Context;
use Xendit\M2Invoice\Helper\ApiRequest;
use Magento\Sales\Model\Order;
use Magento\Framework\App\RequestInterface;
use \Magento\Framework\Phrase;

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
    protected $_canCapture = false;

    protected $cryptoHelper;
    protected $dataHelper;
    protected $apiHelper;
    protected $request;

    public function __construct(
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
        Data $dataHelper,
        ApiRequest $apiHelper,
        RequestInterface $httpRequest
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

        $this->cryptoHelper = $cryptoHelper;
        $this->dataHelper = $dataHelper;
        $this->apiHelper = $apiHelper;
        $this->request = $httpRequest;
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

        $order->setStatus(Order::STATE_PENDING_PAYMENT);
        $order->setState(Order::STATE_PENDING_PAYMENT);
        $order->setIsNotified(false);
        $order->save();

        $payment->setIsTransactionPending(true);

        try {
            $requestData = array(
                'token_id' => $additionalData['token_id'],
                'amount' => $amount,
                'external_id' => $this->dataHelper->getExternalId($orderId),
                'return_url' => $this->dataHelper->getThreeDSResultUrl($orderId)
            );

            $hosted3DS = $this->request3DS($requestData);
        } catch (\Zend_Http_Client_Exception $e) {
            $errorMsg = $e->getMessage();
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
        } finally {
            if (!empty($errorMsg)) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    new Phrase($errorMsg)
                );
            }
        }
    }

    private function getAdditionalData()
    {
        static $data = [];
        if (count($data) < 1) {
            $data = (array) $this->getPaymentMethod();
        }

        return $this->elementFromArray($data, 'additional_data');
    }

    private function getPaymentMethod()
    {
        /**
         * @var array $data
         * Holds submitted JSOn data in a PHP associative array
         */
        static $data = [];
        if (count($data) < 1) {
            $data = (array) $this->dataHelper->jsonData();
        }
        $this->log($data, 'HPS\Heartland\Model\Payment getPaymentMethod Method Called:  ');
        return $this->elementFromArray($data, 'paymentMethod');
    }

    private function elementFromArray($data, $element)
    {
        $r = [];
        if (key_exists($element, $data)) {
            $r = (array) $data[$element];
        }

        return $r;
    }

    private function request3DS($requestData)
    {
        $hosted3DSUrl = $this->dataHelper->getCheckoutUrl() . "/payment/xendit/credit-card/hosted-3ds";
        $hosted3DSMethod = \Zend\Http\Request::METHOD_POST;
        
        try {
            $hosted3DS = $this->apiHelper->request($hosted3DSUrl, $hosted3DSMethod, $requestData, true);
        } catch (\Exception $e) {
            throw $e;
        }

        return $hosted3DS;
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