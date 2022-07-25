<?php

namespace Xendit\M2Invoice\Model\Adminhtml\Source;

use Magento\Framework\Model\Context;
use Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory;
use Magento\Framework\Serialize\Serializer\Json as MagentoSerializerJson;
use Xendit\M2Invoice\External\Serialize\Serializer\Json  as XenditSerializerJson;

/**
 * Class CartRule
 * @package Xendit\M2Invoice\Model\Adminhtml\Source
 */
class CartRule implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @var
     */
    protected $context;

    /**
     * @var \Magento\Framework\App\CacheInterface
     */
    protected $cache;

    /**
     * @var XenditSerializerJson
     */
    protected $serializer;

    /**
     * @var CollectionFactory
     */
    protected $ruleCollectionFactory;

    /**
     * @var MagentoSerializerJson
     */
    protected $magentoSerializerJson;

    /**
     * @var XenditSerializerJson
     */
    protected $xenditSerializerJson;

    /**
     * CartRule constructor.
     * @param Context $context
     * @param CollectionFactory $ruleCollectionFactory
     * @param MagentoSerializerJson $magentoSerializerJson
     * @param XenditSerializerJson $xenditSerializerJson
     */
    public function __construct(
        Context $context,
        CollectionFactory $ruleCollectionFactory,
        MagentoSerializerJson $magentoSerializerJson,
        XenditSerializerJson $xenditSerializerJson
    ) {
        $this->ruleCollectionFactory = $ruleCollectionFactory;
        $this->cache = $context->getCacheManager();
        $this->magentoSerializerJson = $magentoSerializerJson;
        $this->xenditSerializerJson = $xenditSerializerJson;
        if (interface_exists("Magento\Framework\Serialize\Serializer\Json")) {
            $this->serializer = $this->magentoSerializerJson;
        } else {
            $this->serializer = $this->xenditSerializerJson;
        }
    }

    /**
     * @return array|bool|float|int|mixed|string|null
     */
    public function toOptionArray()
    {
        $cachedData = $this->getCache('xendit_available_cart_rules');
        if (!empty($cachedData)) {
            return $cachedData;
        }

        $shoppingCartRules = $this->ruleCollectionFactory->create()->addFieldToSelect("rule_id")->addFieldToSelect("name")->addFieldToFilter("is_active", 1);
        $options = array([
            'value' => '',
            'label' => __('Choose Cart Rule')
        ]);
        foreach ($shoppingCartRules as $shoppingCartRule) {
            $options[] = [
                'value' => $shoppingCartRule->getRuleId(),
                'label' => $shoppingCartRule->getName()
            ];
        }
        $this->storeCache('xendit_available_cart_rules', $options);
        return $options;
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
}
