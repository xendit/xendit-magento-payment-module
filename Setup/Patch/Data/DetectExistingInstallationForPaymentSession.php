<?php

namespace Xendit\M2Invoice\Setup\Patch\Data;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Psr\Log\LoggerInterface;

/**
 * One-time data patch: detect whether this is an existing or new installation.
 *
 * Runs on setup:upgrade. Determines toggle defaults for Payment Session:
 * - Existing merchant (has API keys): toggle OFF (Invoice flow remains default)
 * - New merchant (no API keys): toggle ON (Payment Session is default)
 *
 * Magento enforces setup:upgrade after module updates, so data patches are guaranteed to run.
 *
 * @deprecated As of v14.0.0, this patch is a no-op. Payment Session is always enabled and the
 *             toggle config keys it writes (enable_payment_session, is_existing_merchant_when_ps_introduced)
 *             no longer exist. The patch file is kept so Magento does not attempt to re-run it.
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
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param WriterInterface $configWriter
     * @param LoggerInterface $logger
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        WriterInterface $configWriter,
        LoggerInterface $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->configWriter = $configWriter;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        // scopeConfig->getValue auto-decrypts fields with backend_model="Encrypted",
        // so we get the plaintext API key directly — no manual decryption needed.
        // Read from default scope — setup:upgrade runs in CLI where store scope may not resolve.
        $testKey = $this->scopeConfig->getValue('payment/xendit/test_private_key');
        $liveKey = $this->scopeConfig->getValue('payment/xendit/private_key');

        $hasApiKeys = !empty(trim($testKey ?? '')) || !empty(trim($liveKey ?? ''));

        $this->logger->info('[Xendit] DetectExistingInstallationForPaymentSession', [
            'test_key_present' => !empty(trim($testKey ?? '')),
            'live_key_present' => !empty(trim($liveKey ?? '')),
            'has_api_keys' => $hasApiKeys,
        ]);

        if ($hasApiKeys) {
            // Existing merchant: Payment Session OFF by default, show toggle
            $this->logger->info('[Xendit] DetectExisting: EXISTING merchant path — setting enable_payment_session=0');
            $this->configWriter->save('payment/xendit/is_existing_merchant_when_ps_introduced', '1');
            $this->configWriter->save('payment/xendit/enable_payment_session', '0');
        } else {
            // New merchant: Payment Session ON by default, hide toggle
            $this->logger->info('[Xendit] DetectExisting: NEW merchant path — setting enable_payment_session=1');
            $this->configWriter->save('payment/xendit/is_existing_merchant_when_ps_introduced', '0');
            $this->configWriter->save('payment/xendit/enable_payment_session', '1');
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
