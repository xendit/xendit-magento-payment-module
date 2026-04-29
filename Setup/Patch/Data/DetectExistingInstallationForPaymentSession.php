<?php

namespace Xendit\M2Invoice\Setup\Patch\Data;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * One-time data patch: detect whether this is an existing or new installation.
 *
 * Runs on setup:upgrade. Determines toggle defaults for Payment Session:
 * - Existing merchant (has API keys): toggle OFF (Invoice flow remains default)
 * - New merchant (no API keys): toggle ON (Payment Session is default)
 *
 * Magento enforces setup:upgrade after module updates, so data patches are guaranteed to run.
 */
class DetectExistingInstallationForPaymentSession implements DataPatchInterface
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param WriterInterface $configWriter
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        WriterInterface $configWriter
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->configWriter = $configWriter;
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        // Check if merchant has any API keys configured (test or live)
        $testKey = $this->scopeConfig->getValue('payment/xendit/test_private_key', ScopeInterface::SCOPE_STORE);
        $liveKey = $this->scopeConfig->getValue('payment/xendit/private_key', ScopeInterface::SCOPE_STORE);

        $hasApiKeys = !empty($testKey) || !empty($liveKey);

        if ($hasApiKeys) {
            // Existing merchant: Payment Session OFF by default, show toggle
            $this->configWriter->save('payment/xendit/is_existing_merchant_when_ps_introduced', 'yes');
            $this->configWriter->save('payment/xendit/enable_payment_session', 'no');
        } else {
            // New merchant: Payment Session ON by default, hide toggle
            $this->configWriter->save('payment/xendit/is_existing_merchant_when_ps_introduced', 'no');
            $this->configWriter->save('payment/xendit/enable_payment_session', 'yes');
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
