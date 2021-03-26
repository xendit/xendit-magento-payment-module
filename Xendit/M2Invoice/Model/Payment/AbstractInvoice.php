<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\Method\Logger;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\SalesRule\Model\RuleRepository;
use Magento\Store\Model\StoreManagerInterface;
use Xendit\M2Invoice\Helper\ApiRequest;
use Xendit\M2Invoice\Helper\LogDNA;
use Magento\Quote\Api\CartRepositoryInterface;
use Xendit\M2Invoice\Logger\Logger as XenditLogger;
use Magento\Framework\Serialize\Serializer\Json as MagentoSerializerJson;
use Xendit\M2Invoice\External\Serialize\Serializer\Json  as XenditSerializerJson;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Xendit\M2Invoice\Helper\ErrorHandler;
use Magento\Sales\Model\Service\InvoiceService;
use Xendit\M2Invoice\Helper\Checkout;
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\DB\Transaction as DbTransaction;

/**
 * Class AbstractInvoice
 * @package Xendit\M2Invoice\Model\Payment
 */
class AbstractInvoice extends AbstractMethod
{

    /**
     * @var
     */
    protected $_minAmount;

    /**
     * @var
     */
    protected $_maxAmount;

    /**
     * @var
     */
    protected $methodCode;

    /**
     * @var \Xendit\M2Invoice\Helper\Data
     */
    protected $dataHelper;

    /**
     * @var ApiRequest
     */
    protected $apiHelper;

    /**
     * @var LogDNA
     */
    protected $logDNA;

    /**
     * @var \Magento\Framework\App\CacheInterface
     */
    protected $cache;

    /**
     * @var DataObjectFactory
     */
    protected $dataObjectFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var RuleRepository
     */
    protected $ruleRepo;

    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var XenditLogger
     */
    protected $xenditLogger;

    /**
     * @var MagentoSerializerJson
     */
    protected $magentoSerializerJson;

    /**
     * @var XenditSerializerJson
     */
    protected $xenditSerializerJson;

    /**
     * @var XenditSerializerJson
     */
    protected $serializer;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var ErrorHandler
     */
    protected $errorHandler;

    /**
     * @var InvoiceService
     */
    protected $invoiceService;

    /**
     * @var Checkout
     */
    protected $checkoutHelper;

    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @var DbTransaction
     */
    protected $dbTransaction;


    /**
     * AbstractInvoice constructor.
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param Data $paymentData
     * @param ScopeConfigInterface $scopeConfig
     * @param Logger $logger
     * @param ApiRequest $apiHelper
     * @param \Xendit\M2Invoice\Helper\Data $dataHelper
     * @param LogDNA $logDNA
     * @param DataObjectFactory $dataObjectFactory
     * @param StoreManagerInterface $storeManager
     * @param RuleRepository $ruleRepo
     * @param CartRepositoryInterface $quoteRepository
     * @param XenditLogger $xenditLogger
     * @param MagentoSerializerJson $magentoSerializerJson
     * @param XenditSerializerJson $xenditSerializerJson
     * @param CustomerSession $customerSession
     * @param ErrorHandler $errorHandler
     * @param InvoiceService $invoiceService
     * @param Checkout $checkoutHelper
     * @param OrderFactory $orderFactory
     * @param DbTransaction $dbTransaction
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        Data $paymentData,
        ScopeConfigInterface $scopeConfig,
        Logger $logger,
        ApiRequest $apiHelper,
        \Xendit\M2Invoice\Helper\Data $dataHelper,
        LogDNA $logDNA,
        DataObjectFactory $dataObjectFactory,
        StoreManagerInterface $storeManager,
        RuleRepository $ruleRepo,
        CartRepositoryInterface $quoteRepository,
        XenditLogger $xenditLogger,
        MagentoSerializerJson $magentoSerializerJson,
        XenditSerializerJson $xenditSerializerJson,
        CustomerSession $customerSession,
        ErrorHandler $errorHandler,
        InvoiceService $invoiceService,
        Checkout $checkoutHelper,
        OrderFactory $orderFactory,
        DbTransaction $dbTransaction
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger
        );
        $this->_scopeConfig = $scopeConfig;
        $this->dataHelper               = $dataHelper;
        $this->apiHelper                = $apiHelper;
        $this->logDNA                   = $logDNA;
        $this->cache                    = $context->getCacheManager();
        $this->dataObjectFactory        = $dataObjectFactory;
        $this->storeManager             = $storeManager;
        $this->ruleRepo                 = $ruleRepo;
        $this->quoteRepository          = $quoteRepository;
        $this->xenditLogger             = $xenditLogger;
        $this->magentoSerializerJson    = $magentoSerializerJson;
        $this->xenditSerializerJson     = $xenditSerializerJson;
        $this->customerSession          = $customerSession;
        $this->errorHandler             = $errorHandler;
        $this->invoiceService           = $invoiceService;
        $this->checkoutHelper           = $checkoutHelper;
        $this->orderFactory             = $orderFactory;
        $this->dbTransaction            = $dbTransaction;

        if (interface_exists("Magento\Framework\Serialize\Serializer\Json")) {
            $this->serializer = $this->magentoSerializerJson;
        } else {
            $this->serializer = $this->xenditSerializerJson;
        }
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

        $amount = ceil($quote->getSubtotal() + $quote->getShippingAddress()->getShippingAmount());

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

        if (($cardPaymentType === 'popup' && $this->methodCode === 'CCHOSTED') || $this->methodCode === 'CC_INSTALLMENT' || $this->methodCode === 'CC_SUBSCRIPTION') {
            return true;
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

    /**
     * @return array|bool|float|int|mixed|string|null
     * @throws \Exception
     */
    private function getAvailableMethods()
    {
        $cachedData = $this->getCache('xeninvoice_available_method');
        if (!empty($cachedData)) {
            return $cachedData;
        }

        $url =  "https://tpi.xendit.co/payment/xendit/settings/invoice";
        $method = \Zend\Http\Request::METHOD_GET;
        $options = [
            'timeout' => 5
        ];

        try {
            $response = $this->apiHelper->request($url, $method, null, null, null, $options);
        } catch (\Exception $e) {
            throw $e;
        }

        if (!isset($response['available_method'])) {
            return [];
        }

        $this->storeCache('xeninvoice_available_method', $response['available_method']);
        return $response['available_method'];
    }

    /**
     * @param $key
     * @param $data
     */
    protected function storeCache($key, $data)
    {
        $unserializedData = $this->serializer->serialize($data);
        $this->cache->save($unserializedData, $key, [], 120);
    }

    /**
     * @param $key
     * @return array|bool|float|int|mixed|string|null
     */
    protected function getCache($key)
    {
        $data = $this->cache->load($key);
        if ($data === false) {
            return [];
        }
        $serializedData = $this->serializer->unserialize($data);
        return $serializedData;
    }

    /**
     * @return StoreManagerInterface
     */
    protected function getStoreManager()
    {
        return $this->storeManager;
    }

    /**
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function getXenditCallbackUrl()
    {
        $baseUrl = $this->getStoreManager()->getStore()->getBaseUrl(UrlInterface::URL_TYPE_LINK);
        return $baseUrl . 'xendit/checkout/notification';
    }
}