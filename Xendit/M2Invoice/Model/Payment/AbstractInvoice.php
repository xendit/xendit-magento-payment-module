<?php


namespace Xendit\M2Invoice\Model\Payment;

use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Model\Context;
use Magento\Framework\Phrase;
use Magento\Framework\Registry;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\Method\Logger;
use Magento\Payment\Model\Method\AbstractMethod;
use Xendit\M2Invoice\Helper\ApiRequest;
use Xendit\M2Invoice\Helper\LogDNA;
use Xendit\M2Invoice\Enum\LogDNALevel;
use Xendit\M2Invoice\External\Serialize\Serializer\Json;


class AbstractInvoice extends AbstractMethod
{
    protected $_minAmount;
    protected $_maxAmount;
    protected $methodCode;

    protected $dataHelper;
    protected $apiHelper;
    protected $logDNA;
    protected $cache;
    protected $dataObjectFactory;

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
        Json $serializer = null
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

        $this->dataHelper = $dataHelper;
        $this->apiHelper = $apiHelper;
        $this->logDNA = $logDNA;

        $this->cache = $context->getCacheManager();
        $this->dataObjectFactory = $dataObjectFactory;
        $this->serializer = $serializer ?: \Magento\Framework\App\ObjectManager::getInstance()->get(Json::class);
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

        try {
            $availableMethod = $this->getAvailableMethods();

            if (empty($availableMethod)) {
                return true;
            }

            if ( !in_array( strtoupper($this->methodCode), $availableMethod ) ) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            return true;
        }
    }

    private function getAvailableMethods()
    {
        $cachedData = $this->getCache('xeninvoice_available_method');
        if (!empty($cachedData)) {
            return $cachedData;
        }

        $url = $this->dataHelper->getCheckoutUrl() . "/payment/xendit/settings/invoice";
        $method = \Zend\Http\Request::METHOD_GET;
        $options = [
            'timeout' => 5
        ];

        try {
            $response = $this->apiHelper->request($url, $method, null, null, null, $options);
        } catch (\Exception $e) {
            throw $e;
        }

        if ( !isset($response['available_method']) ) {
            return [];
        }

        $this->storeCache('xeninvoice_available_method', $response['available_method']);

        return $response['available_method'];
    }

    protected function storeCache($key, $data)
    {
        $unserializedData = $this->serializer->serialize($data);

        $this->cache->save($unserializedData, $key, [], 120);
    }

    protected function getCache($key)
    {
        $data = $this->cache->load($key);

        if ($data === false) {
            return [];
        }

        $serializedData = $this->serializer->unserialize($data);

        return $serializedData;
    }
}