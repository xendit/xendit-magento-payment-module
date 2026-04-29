<?php

namespace Xendit\M2Invoice\Setup\Patch\Data;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

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
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param WriterInterface $configWriter
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        WriterInterface $configWriter,
        EncryptorInterface $encryptor
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->configWriter = $configWriter;
        $this->encryptor = $encryptor;
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        // API keys are encrypted in core_config_data (backend_model="Encrypted").
        // scopeConfig->getValue returns the raw encrypted ciphertext, which is non-empty
        // even for blank values. We must decrypt first, then check if the plaintext is empty.
        // Read from default scope — keys are typically saved at default, and setup:upgrade
        // runs in CLI where store scope may not resolve correctly.
        $testKeyEncrypted = $this->scopeConfig->getValue('payment/xendit/test_private_key');
        $liveKeyEncrypted = $this->scopeConfig->getValue('payment/xendit/private_key');

        $testKey = $testKeyEncrypted ? $this->encryptor->decrypt($testKeyEncrypted) : '';
        $liveKey = $liveKeyEncrypted ? $this->encryptor->decrypt($liveKeyEncrypted) : '';

        $hasApiKeys = !empty(trim($testKey)) || !empty(trim($liveKey));

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
