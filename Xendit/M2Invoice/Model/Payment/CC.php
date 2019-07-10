<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Framework\Phrase;
use Magento\Framework\UrlInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseFactory;
use Magento\Framework\Model\Context;
use Magento\Sales\Model\Order;
use Xendit\M2Invoice\Helper\ApiRequest;
use Xendit\M2Invoice\Helper\Crypto;
use Xendit\M2Invoice\Helper\Data;

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
    protected $url;
    protected $responseFactory;

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
        Data $dataHelper,
        ApiRequest $apiHelper,
        RequestInterface $httpRequest,
        UrlInterface $url,
        ResponseFactory $responseFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
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
        $this->url = $url;
        $this->responseFactory = $responseFactory;
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

        $payment->setIsTransactionPending(true);

        try {
            $requestData = array(
                'token_id' => $additionalData['token_id'],
                'amount' => $amount,
                'external_id' => $this->dataHelper->getExternalId($orderId),
                'return_url' => $this->dataHelper->getThreeDSResultUrl($orderId)
            );

            $charge = $this->requestCharge($requestData);

            $chargeError = isset($charge['error_code']) ? $charge['error_code'] : null;
            if ( $chargeError == 'AUTHENTICATION_ID_MISSING_ERROR' ) {
                $this->handle3DSFlow($requestData, $payment, $order);

                return $this;
            }

            if ( $chargeError !== null ) {
                $this->processFailedPayment($order, $payment, $charge);
            }

            if ($charge['status'] === 'CAPTURED') {
                $transactionId = $charge['id'];
                $payment->setTransactionId($transactionId);
                $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE, null, true);

                $payment->setAdditionalInformation('xendit_charge_id', $transactionId);
            } else {
                $this->processFailedPayment($order, $payment, $charge);
            }
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

        return $this;
    }

    protected function getObjectManager()
    {
        return \Magento\Framework\App\ObjectManager::getInstance();
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

    private function handle3DSFlow($requestData, $payment, $order)
    {
        $hosted3DS = $this->request3DS($requestData);

        if ('IN_REVIEW' === $hosted3DS['status']) {
            $hostedUrl = $hosted3DS['redirect']['url'];
            $hostedId = $hosted3DS['id'];
            $payment->setAdditionalInformation('payment_gateway', 'xendit');
            $payment->setAdditionalInformation('xendit_redirect_url', $hostedUrl);
            $payment->setAdditionalInformation('xendit_hosted_3ds_id', $hostedId);

            $order->setState(Order::STATE_PAYMENT_REVIEW)
                ->setStatus(Order::STATE_PAYMENT_REVIEW)
                ->addStatusHistoryComment("Xendit payment waiting authentication. Transaction ID: $hostedId");
            $order->save();
        }

        return;
    }

    private function processFailedPayment($order, $payment, $charge = [])
    {
        if ($charge === []) {
            $failureReason = 'Unexpected Error';
        } else {
            $failureReason = isset($charge['failure_reason']) ? $charge['failure_reason'] : 'Unexpected Error';
        }

        $payment->setAdditionalInformation('xendit_failure_reason', $failureReason);
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

    private function requestCharge($requestData)
    {
        $chargeUrl = $this->dataHelper->getCheckoutUrl() . "/payment/xendit/credit-card/charges";
        $chargeMethod = \Zend\Http\Request::METHOD_POST;

        try {
            $hosted3DS = $this->apiHelper->request($chargeUrl, $chargeMethod, $requestData);
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
