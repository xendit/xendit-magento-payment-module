<?php

namespace Xendit\M2Invoice\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Sales\Model\Order\StatusFactory;
use Magento\Sales\Model\ResourceModel\Order\StatusFactory as StatusResourceFactory;

class AddXenditCustomOrderStatus implements DataPatchInterface
{
    const CUSTOM_STATUS_CODE = 'insufficient_inventory';
    const CUSTOM_STATE_CODE = 'insufficient_inventory';
    const CUSTOM_STATUS_LABEL = 'Insufficient Inventory';

    /** @var StatusFactory $statusFactory */
    protected $statusFactory;

    /** @var StatusResourceFactory $statusResourceFactory */
    protected $statusResourceFactory;

    /**
     * @param StatusFactory $statusFactory
     * @param StatusResourceFactory $statusResourceFactory
     */
    public function __construct(
        StatusFactory $statusFactory,
        StatusResourceFactory $statusResourceFactory
    ) {
        $this->statusFactory = $statusFactory;
        $this->statusResourceFactory = $statusResourceFactory;
    }

    public function apply()
    {
        $statusResource = $this->statusResourceFactory->create();
        $status = $this->statusFactory->create();
        $status->setData([
            'status' => self::CUSTOM_STATUS_CODE,
            'label' => self::CUSTOM_STATUS_LABEL,
        ]);
        try {
            $statusResource->save($status);
        } catch (\Magento\Framework\Exception\AlreadyExistsException $exception) {
            return;
        }
        $status->assignState(self::CUSTOM_STATE_CODE, true, true);
    }

    public function getAliases()
    {
        return [];
    }

    public static function getDependencies()
    {
        return [];
    }
}
