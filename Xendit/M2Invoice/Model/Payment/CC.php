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
use Xendit\M2Invoice\Helper\Data as XenditHelper;
use Magento\Payment\Model\Method\Cc as MagentoPaymentMethodCc;
use Magento\Framework\Registry;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Model\Method\Logger;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Class CC
 * @package Xendit\M2Invoice\Model\Payment
 */
class CC extends MagentoPaymentMethodCc
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

    /**
     * @var Crypto
     */
    protected $cryptoHelper;

    /**
     * @var XenditHelper
     */
    protected $dataHelper;

    /**
     * @var ApiRequest
     */
    protected $apiHelper;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var UrlInterface
     */
    protected $url;

    /**
     * @var ResponseFactory
     */
    protected $responseFactory;

    /**
     * @var RuleRepository
     */
    protected $ruleRepo;

    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * CC constructor.
     * @param Crypto $cryptoHelper
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param PaymentHelper $paymentData
     * @param ScopeConfigInterface $scopeConfig
     * @param Logger $logger
     * @param ModuleListInterface $moduleList
     * @param TimezoneInterface $localeDate
     * @param XenditHelper $dataHelper
     * @param ApiRequest $apiHelper
     * @param RequestInterface $httpRequest
     * @param UrlInterface $url
     * @param ResponseFactory $responseFactory
     * @param RuleRepository $ruleRepo
     * @param CartRepositoryInterface $quoteRepository
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Crypto $cryptoHelper,
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        PaymentHelper $paymentData,
        ScopeConfigInterface $scopeConfig,
        Logger $logger,
        ModuleListInterface $moduleList,
        TimezoneInterface $localeDate,
        XenditHelper $dataHelper,
        ApiRequest $apiHelper,
        RequestInterface $httpRequest,
        UrlInterface $url,
        ResponseFactory $responseFactory,
        RuleRepository $ruleRepo,
        CartRepositoryInterface $quoteRepository,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
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
        $this->ruleRepo = $ruleRepo;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * @param CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(CartInterface $quote = null)
    {
        if ($quote === null) {
            return false;
        }

        if ($this->dataHelper->getIsActive() === '0') {
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
    }

    /**
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return MagentoPaymentMethodCc|void
     */
    public function capture(InfoInterface $payment, $amount)
    {
        //todo add functionality later
    }

    /**
     * @param InfoInterface $payment
     * @param float $amount
     * @return $this|MagentoPaymentMethodCc
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function refund(InfoInterface $payment, $amount)
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
            throw new LocalizedException(
                __("Refund not available because there is no capture")
            );
        }
    }

    /**
     * @param InfoInterface $payment
     * @param float $amount
     * @return $this|MagentoPaymentMethodCc
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function authorize(InfoInterface $payment, $amount)
    {
        $payment->setIsTransactionPending(true);
        $additionalData = $this->getAdditionalData(); 
        
        if (!$additionalData) { //coming from callback - no need to process & charge the order
            return $this;
        }

        $order = $payment->getOrder();
        $quoteId = $order->getQuoteId();
        $quote = $this->quoteRepository->get($quoteId);

        if ($quote->getIsMultiShipping()) {
            return $this;
        }

        $orderId = $order->getRealOrderId();

        $cvn = !empty($additionalData['cc_cid']) ? $additionalData['cc_cid'] : null;
        $bin = !empty($additionalData['cc_number']) ? substr($additionalData['cc_number'], 0, 6) : null;

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
                $requestData['promotion'] = json_encode([
                    'original_amount' => $rawAmount,
                    'title' => $promoResult['rule']->getName(),
                    'promo_amount' => $amount,
                    'promo_reference' => $order->getAppliedRuleIds(),
                    'type' => $this->dataHelper->mapSalesRuleType($promoResult['rule']->getSimpleAction())
                ]);
            }
            
            $charge = $this->requestCharge($requestData);
            

            $chargeError = isset($charge['error_code']) ? $charge['error_code'] : null;
            if ($chargeError == 'EXTERNAL_ID_ALREADY_USED_ERROR') {
                $newRequestData = array_replace($requestData, array(
                    'external_id' => $this->dataHelper->getExternalId($orderId, true)
                ));
                $charge = $this->requestCharge($newRequestData);
            }
            else if ($chargeError == 'AUTHENTICATION_ID_MISSING_ERROR') {
                $this->handle3DSFlow($requestData, $payment, $order);

                return $this;
            }
            else if ($chargeError) {
                throw new LocalizedException(
                    __($charge['message'])
                );
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
                throw new LocalizedException(
                    new Phrase($errorMsg)
                );
            }
        }

        return $this;
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    private function getAdditionalData()
    {
        static $data = [];
        if (count($data) < 1) {
            $data = (array) $this->getPaymentMethod();
        }

        return $this->elementFromArray($data, 'additional_data');
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\FileSystemException
     */
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

    /**
     * @param $data
     * @param $element
     * @return array
     */
    private function elementFromArray($data, $element)
    {
        $r = [];
        if (key_exists($element, $data)) {
            $r = (array) $data[$element];
        }

        return $r;
    }

    /**
     * @param $requestData
     * @param $payment
     * @param $order
     * @throws \Exception
     */
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

    /**
     * @param $payment
     * @param $refund
     * @param $canRefundMore
     * @throws LocalizedException
     */
    private function handleRefundResult($payment, $refund, $canRefundMore)
    {
        if (isset($refund['error_code'])) {
            throw new LocalizedException(
                __($refund['message'])
            );
        }

        if ($refund['status'] == 'FAILED') {
            throw new LocalizedException(
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

    /**
     * @param $order
     * @param $payment
     * @param array $charge
     */
    private function processFailedPayment($order, $payment, $charge = [])
    {
        if ($charge === []) {
            $failureReason = 'Unexpected Error';
        } else {
            $failureReason = isset($charge['failure_reason']) ? $charge['failure_reason'] : 'Unexpected Error';
        }

        $payment->setAdditionalInformation('xendit_failure_reason', $failureReason);
    }

    /**
     * @param $requestData
     * @return mixed
     * @throws \Exception
     */
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

    /**
     * @param $requestData
     * @return mixed
     * @throws \Exception
     */
    private function requestCharge($requestData)
    {
        $chargeUrl = $this->dataHelper->getCheckoutUrl() . "payment/xendit/credit-card/charges";
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
        $refundUrl = $this->dataHelper->getCheckoutUrl() . "/credit-card/charges/:$chargeId/refunds";
        $refundMethod = \Zend\Http\Request::METHOD_POST;

        try {
            $refund = $this->apiHelper->request($refundUrl, $refundMethod, $requestData);
        } catch (\Exception $e) {
            throw $e;
        }

        return $refund;
    }

    /**
     * @param $param
     * @param $message
     */
    private function log($param, $message)
    {
        try {
            $this->_logger->log(100, $message . var_export($param, true));
        } catch (\Exception $e) {
            $this->_logger->log(100, $message . var_export($param, true));
        }
    }

    /**
     * @param $bin
     * @param $order
     * @return array|bool
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
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
