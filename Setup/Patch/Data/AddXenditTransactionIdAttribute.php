<?php

namespace Xendit\M2Invoice\Setup\Patch\Data;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Sales\Setup\SalesSetupFactory;

class AddXenditTransactionIdAttribute implements DataPatchInterface
{
    /**
     * @var SalesSetupFactory
     */
    private $salesSetupFactory;

    /**
     * @param SalesSetupFactory $salesSetupFactory
     */
    public function __construct(
        SalesSetupFactory $salesSetupFactory
    ) {
        $this->salesSetupFactory = $salesSetupFactory;
    }

    public function apply()
    {
        /**
         * Add 'xendit_transaction_id' attributes for order
         */
        $salesSetup = $this->salesSetupFactory->create();
        $salesSetup->addAttribute('order', 'xendit_transaction_id', [
            'type' => 'varchar',
            'visible' => false,
            'required' => false
        ]);

        return $this;
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
