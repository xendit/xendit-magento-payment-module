<?php


namespace Xendit\M2Invoice\Model\Payment;

use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Model\Context;
use Magento\Framework\Phrase;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\Method\Logger;
use Magento\Payment\Model\Method\AbstractMethod;
use Xendit\M2Invoice\Helper\ApiRequest;
use Xendit\M2Invoice\Helper\LogDNA;
use Xendit\M2Invoice\Enum\LogDNALevel;


class AbstractInvoice extends AbstractMethod
{
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
        CacheInterface $cache,
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

        $this->cache = $cache;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->serializer = $serializer ?: \Magento\Framework\App\ObjectManager::getInstance()->get(Json::class);
    }

    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        try {
            $availableMethod = $this->getAvailableMethods();

            if (empty($invoiceSettings)) {
                return true;
            }

            if (isset($invoiceSettings['error_code'])) {
                return true;
            }

            foreach ($availableMethod as $method) {
                if (strpos(strtoupper($this->_code), $method) === FALSE) {
                    return false;
                }
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

        try {
            $response = $this->apiHelper->request($url, $method);
        } catch (\Exception $e) {
            throw $e;
        }

        if ( !isset($response['available_method']) ) {
            return [];
        }
        $this->logDNA->log(LogDNALevel::INFO, "before storing cache");

        $this->storeCache('xeninvoice_available_method', $response['available_method']);

        return $response['available_method'];
    }

    protected function storeCache($key, $data)
    {
        $unserializedData = $this->serializer->serialize($data);

        $this->cache->save($unserializedData, $key, [], 120000);

        $this->logDNA->log(LogDNALevel::INFO, "storing cache for");
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