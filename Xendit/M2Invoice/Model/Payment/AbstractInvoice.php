<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\Method\Logger;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\SalesRule\Model\RuleRepository;
use Magento\Store\Model\StoreManagerInterface;
use Xendit\M2Invoice\Helper\ApiRequest;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Serialize\Serializer\Json as MagentoSerializerJson;
use Xendit\M2Invoice\External\Serialize\Serializer\Json  as XenditSerializerJson;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Customer\Model\Session as CustomerSession;

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
     * @var \Magento\Framework\App\CacheInterface
     */
    protected $cache;

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
     * @param StoreManagerInterface $storeManager
     * @param RuleRepository $ruleRepo
     * @param CartRepositoryInterface $quoteRepository
     * @param MagentoSerializerJson $magentoSerializerJson
     * @param XenditSerializerJson $xenditSerializerJson
     * @param CustomerSession $customerSession
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
        StoreManagerInterface $storeManager,
        RuleRepository $ruleRepo,
        CartRepositoryInterface $quoteRepository,
        MagentoSerializerJson $magentoSerializerJson,
        XenditSerializerJson $xenditSerializerJson,
        CustomerSession $customerSession
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

        $this->dataHelper               = $dataHelper;
        $this->apiHelper                = $apiHelper;
        $this->cache                    = $context->getCacheManager();
        $this->storeManager             = $storeManager;
        $this->ruleRepo                 = $ruleRepo;
        $this->quoteRepository          = $quoteRepository;
        $this->magentoSerializerJson    = $magentoSerializerJson;
        $this->xenditSerializerJson     = $xenditSerializerJson;
        $this->customerSession          = $customerSession;

        if (interface_exists("Magento\Framework\Serialize\Serializer\Json")) {
            $this->serializer = $this->magentoSerializerJson;
        } else {
            $this->serializer = $this->xenditSerializerJson;
        }
    }

    /**
     * @param CartInterface|null $quote
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function isAvailable(CartInterface $quote = null)
    {
        if ($quote === null || !$this->dataHelper->getIsActive() || !$this->dataHelper->getIsPaymentActive($this->_code)) {
            return false;
        }

        if(!in_array($this->_code, ['cc', 'cc_subscription']) && !$this->isAvailableOnCurrency()){
            return false;
        }

        $amount = ceil($quote->getSubtotal() + $quote->getShippingAddress()->getShippingAmount());
        if ($amount < $this->dataHelper->getPaymentMinOrderAmount($this->_code) || $amount > $this->dataHelper->getPaymentMaxOrderAmount($this->_code)) {
            return false;
        }

        return true;
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

    /**
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function getCurrency()
    {
        return $this->storeManager->getStore()->getCurrentCurrencyCode();
    }

    /**
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function isAvailableOnCurrency()
    {
        return $this->dataHelper->isAvailableOnCurrency($this->_code, $this->getCurrency());
    }
}
