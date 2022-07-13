<?php
/**
 * Copyright © 2018 Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Xendit\M2Invoice\Setup;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Sales\Setup\SalesSetupFactory;

class InstallData implements InstallDataInterface
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
     * @param ModuleContextInterface   $context
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        /** @var \Magento\Sales\Setup\SalesSetup $salesSetup */
        $salesSetup = $this->salesSetupFactory->create(['setup' => $setup]);

        /**
         * Add 'xendit_transaction_id' attributes for order
         */
        $options = ['type' => 'varchar', 'visible' => false, 'required' => false];
        $salesSetup->addAttribute('order', 'xendit_transaction_id', $options);

        // Add index
        $setup->getConnection()->addIndex(
            $setup->getTable('sales_order'),
            $this->resourceConnection->getIdxName('sales_order', ['xendit_transaction_id']),
            ['xendit_transaction_id']
        );
    }
}
