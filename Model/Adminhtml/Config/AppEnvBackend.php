<?php

namespace Xendit\M2Invoice\Model\Adminhtml\Config;

use Magento\Framework\App\Config\Storage\WriterInterface;

/**
 * Backend model for xendit_app_env.
 *
 * Auto-syncs xendit_url when the deployment environment is changed.
 * This ensures the plugin always connects to the correct TPI Gateway
 * for the selected environment.
 */
class AppEnvBackend extends \Magento\Framework\App\Config\Value
{
    private const URL_MAP = [
        'production' => 'https://tpi-gateway.xendit.co',
        'staging'    => 'https://tpi-gateway-live.ap-southeast-1.stg.tidnex.dev',
    ];

    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     * @param WriterInterface $configWriter
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        WriterInterface $configWriter,
        ?\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        ?\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
        $this->configWriter = $configWriter;
    }

    /**
     * After saving xendit_app_env, sync xendit_url to match the selected environment.
     *
     * @return self
     */
    public function afterSave(): self
    {
        $env = $this->getValue();
        $url = self::URL_MAP[$env] ?? self::URL_MAP['production'];

        $this->configWriter->save(
            'payment/xendit/xendit_url',
            $url,
            $this->getScope(),
            $this->getScopeId()
        );

        // Invalidate config cache so the new xendit_url takes effect immediately
        // (parent::afterSave only cleans cache for xendit_app_env, not the side-effect write)
        $this->cacheTypeList->invalidate(\Magento\Framework\App\Cache\Type\Config::TYPE_IDENTIFIER);

        return parent::afterSave();
    }
}
