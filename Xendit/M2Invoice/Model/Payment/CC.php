<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Framework\Phrase;
use Magento\Framework\UrlInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseFactory;
use Magento\Framework\Model\Context;
use Magento\Sales\Model\Order;
use Magento\SalesRule\Model\RuleRepository;
use Xendit\M2Invoice\Helper\ApiRequest;
use Xendit\M2Invoice\Helper\Crypto;
use Xendit\M2Invoice\Helper\Data;
use Xendit\M2Invoice\Helper\LogDNA;
use Xendit\M2Invoice\Enum\LogDNALevel;

class CC extends \Magento\Payment\Model\Method\Cc
{
    const CODE = 'cc';
    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = self::CODE;
    protected $_minAmount = 5000;
    protected $_maxAmount = 200000000;
    protected $methodCode = 'CCFORM';

    protected $_isGateway = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;

    protected $cryptoHelper;
    protected $dataHelper;
    protected $apiHelper;
    protected $request;
    protected $url;
    protected $responseFactory;
    protected $logdnaHelper;
    protected $ruleRepo;

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
        LogDNA $logdnaHelper,
        RuleRepository $ruleRepo,
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
        $this->logdnaHelper = $logdnaHelper;
        $this->ruleRepo = $ruleRepo;
    }

    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if ($quote === null) {
            return false;
        }

        $amount = $quote->getBaseGrandTotal();

        if ($amount < $this->_minAmount || $amount > $this->_maxAmount) {
            return false;
        }

        $allowedMethod = $this->dataHelper->getAllowedMethod();

        if ($allowedMethod === 'specific') {
            $chosenMethods = $this->dataHelper->getChosenMethods();
            $currentCode = $this->_code;

            if ($currentCode === 'cchosted') {
                $currentCode = 'cc';
            }

            if (!in_array($currentCode, explode(',', $chosenMethods))) {
                return false;
            }
        }

        $cardPaymentType = $this->dataHelper->getCardPaymentType();

        if ($cardPaymentType === 'form') {
            return true;
        } else {
            return false;
        }

        try {
            $availableMethod = $this->getAvailableMethods();

            if (empty($availableMethod)) {
                return true;
            }

            if (!in_array(strtoupper($this->methodCode), $availableMethod)) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            return true;
        }
    }

    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        //todo add functionality later
    }

    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $chargeId = $payment->getParentTransactionId();

        if ($chargeId) {
            $order = $payment->getOrder();
            $orderId = $order->getRealOrderId();
            $canRefundMore = $payment->getCreditmemo()->getInvoice()->canRefund();
            $isFullRefund = !$canRefundMore &&
                0 == (double)$order->getBaseTotalOnlineRefunded() + (double)$order->getBaseTotalOfflineRefunded();

            
            $refundData = [
                'amount' => $amount,
                'external_id' => $this->dataHelper->getExternalId($orderId, true)
            ];
            $refund = $this->requestRefund($chargeId, $refundData);

            $this->handleRefundResult($payment, $refund, $canRefundMore);

            return $this;
        } else {
            throw new \Magento\Framework\Exception\LocalizedException(
                __("Refund not available because there is no capture")
            );
        }
    }

    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $additionalData = $this->getAdditionalData();

        if (!isset($additionalData['token_id'])) {
            return $this;
        }

        $order = $payment->getOrder();
        $orderId = $order->getRealOrderId();
        
        $payment->setIsTransactionPending(true);

        $cvn = isset($additionalData['cc_cid']) ? $additionalData['cc_cid'] : null;
        $bin = isset($additionalData['cc_number']) ? substr($additionalData['cc_number'], 0, 6) : null;

        try {
            $promoResult = $this->calculatePromo($bin, $order);
            $rawAmount = ceil($order->getSubtotal() + $order->getShippingAmount());
            $requestData = array(
                'token_id' => $additionalData['token_id'],
                'card_cvn' => $cvn,
                'amount' => $amount,
                'external_id' => $this->dataHelper->getExternalId($orderId),
                'return_url' => $this->dataHelper->getThreeDSResultUrl($orderId)
            );

            if (!$promoResult['has_promo']) {
                $requestData['amount'] = $rawAmount;

                $invalidDiscountAmount = $order->getBaseDiscountAmount();
                $order->setBaseDiscountAmount(0);
                $order->setBaseGrandTotal($order->getBaseGrandTotal() - $invalidDiscountAmount);

                $invalidDiscountAmount = $order->getDiscountAmount();
                $order->setDiscountAmount(0);
                $order->setGrandTotal($order->getGrandTotal() - $invalidDiscountAmount);

                $order->setBaseTotalDue($order->getBaseGrandTotal());
                $order->setTotalDue($order->getGrandTotal());

                $payment->setBaseAmountOrdered($order->getBaseGrandTotal());
                $payment->setAmountOrdered($order->getGrandTotal());

                $payment->setAmountAuthorized($order->getGrandTotal());
                $payment->setBaseAmountAuthorized($order->getBaseGrandTotal());
            } else {
                $requestData['promotion'] = json_encode(array(
                    'original_amount' => $rawAmount,
                    'title' => $promoResult['rule']->getName(),
                    'promo_amount' => $amount,
                    'promo_reference' => $order->getAppliedRuleIds(),
                    'type' => $this->dataHelper->mapSalesRuleType($promoResult['rule']->getSimpleAction())
                ));
            }

            $charge = $this->requestCharge($requestData);

            $chargeError = isset($charge['error_code']) ? $charge['error_code'] : null;
            if ($chargeError == 'EXTERNAL_ID_ALREADY_USED_ERROR') {
                $newRequestData = array_replace($requestData, array(
                    'external_id' => $this->dataHelper->getExternalId($orderId, true)
                ));
                $charge = $this->requestCharge($newRequestData);
            }

            $chargeError = isset($charge['error_code']) ? $charge['error_code'] : null;
            if ($chargeError == 'AUTHENTICATION_ID_MISSING_ERROR') {
                $this->handle3DSFlow($requestData, $payment, $order);

                return $this;
            }

            if ($chargeError !== null) {
                $this->processFailedPayment($order, $payment, $charge);
            }

            if ($charge['status'] === 'CAPTURED') {
                $transactionId = $charge['id'];

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
                $this->logdnaHelper->log(LogDNALevel::ERROR, 'HTTP Error: ' . $errorMsg);
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
        unset($requestData['card_cvn']);
        $hosted3DSRequestData = array_replace([], $requestData);
        unset($hosted3DSRequestData['promotion']);
        $hosted3DS = $this->request3DS($hosted3DSRequestData);

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

    private function handleRefundResult($payment, $refund, $canRefundMore)
    {
        if (isset($refund['error_code'])) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __($refund['message'])
            );
        }

        if ($refund['status'] == 'FAILED') {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Refund failed, please check Xendit dashboard')
            );
        }

        $payment->setTransactionId(
            $refund['id']
        )->setIsTransactionClosed(
            1
        )->setShouldCloseParentTransaction(
            !$canRefundMore
        );
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

    private function requestRefund($chargeId, $requestData)
    {
        $refundUrl = $this->dataHelper->getCheckoutUrl() . "/payment/xendit/credit-card/charges/$chargeId/refund";
        $refundMethod = \Zend\Http\Request::METHOD_POST;

        try {
            $refund = $this->apiHelper->request($refundUrl, $refundMethod, $requestData);
        } catch (\Exception $e) {
            throw $e;
        }

        return $refund;
    }

    private function log($param, $message)
    {
        try {
            $this->_logger->log(100, $message . var_export($param, true));
        } catch (\Exception $e) {
            $this->_logger->log(100, $message . var_export($param, true));
        }
    }

    private function calculatePromo($bin, $order)
    {
        $ruleIds = $order->getAppliedRuleIds();
        $enabledPromotions = $this->dataHelper->getEnabledPromo();

        if (empty($ruleIds) || empty($enabledPromotions)) {
            return false;
        }

        $ruleIds = explode(',', $ruleIds);

        foreach ($ruleIds as $ruleId) {
            foreach ($enabledPromotions as $promotion) {
                if ($promotion['rule_id'] === $ruleId && in_array($bin, $promotion['bin_list'])) {
                    $rule = $this->ruleRepo->getById($ruleId);
                    return [
                        'has_promo' => true,
                        'rule' => $rule,
                    ];
                }
            }
        }

        return [
            'has_promo' => false
        ];
    }
}
