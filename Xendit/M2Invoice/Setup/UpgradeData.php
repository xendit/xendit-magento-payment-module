<?php

namespace Xendit\M2Invoice\Setup;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Sales\Setup\SalesSetupFactory;

/**
 * Class UpgradeData*/
class UpgradeData implements UpgradeDataInterface
{
    /**
     * Sales setup factory
     *
     * @var SalesSetupFactory
     */
    private $salesSetupFactory;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @param SalesSetupFactory $salesSetupFactory
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        SalesSetupFactory $salesSetupFactory,
        ResourceConnection $resourceConnection
    ) {
        $this->salesSetupFactory = $salesSetupFactory;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function upgrade(
        ModuleDataSetupInterface $setup,
        ModuleContextInterface   $context
    ) {
        if (version_compare($context->getVersion(), "3.9.1", "<")) {
            $salesSetup = $this->salesSetupFactory->create(['setup' => $setup]);

            $salesSetup->addAttribute(
                'order',
                'xendit_transaction_id',
                [
                    'type' => 'varchar', 'visible' => false, 'required' => false
                ]
            );

            // Add index
            $setup->getConnection()->addIndex(
                $setup->getTable('sales_order'),
                $this->resourceConnection->getIdxName('sales_order', ['xendit_transaction_id']),
                ['xendit_transaction_id']
            );
        }
    }
}
