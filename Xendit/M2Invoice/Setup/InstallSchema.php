<?php

namespace Xendit\M2Invoice\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class InstallSchema extends InstallSchemaInterface
{
    /**
     * Xendit Invoice ID Column
     */
    const XENDIT_INVOICE_ID = 'xendit_invoice_id';

    /**
     * Xendit expiration date
     */
    const XENDIT_INVOICE_EXPIRATION_DATE = 'xendit_invoice_exp_date';

    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        $setup->getConnection()->addColumn(
            $setup->getTable('sales_order'),
            self::XENDIT_INVOICE_ID,
            [
                'type' => Table::TYPE_TEXT,
                'size' => 255,
                'nullable' => true,
                'comment' => 'Invoice ID'
            ]
        );

        $setup->getConnection()->addColumn(
            $setup->getTable('sales_order'),
            self::XENDIT_INVOICE_EXPIRATION_DATE,
            [
                'type' => Table::TYPE_DATETIME,
                'size' => 255,
                'nullable' => true,
                'comment' => 'Invoice Expiration Date'
            ]
        );

        $setup->endSetup();
    }
}