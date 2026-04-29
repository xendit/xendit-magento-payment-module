<?php

namespace Xendit\M2Invoice\Model\Adminhtml\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Source model for xendit_app_env dropdown.
 *
 * Controls which Xendit infrastructure the plugin connects to (staging vs production).
 * This is separate from xendit_env (test/live) which controls the Xendit app_mode
 * (which API keys are used).
 */
class AppEnv implements OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'production', 'label' => __('Production')],
            ['value' => 'staging', 'label' => __('Staging')],
        ];
    }
}
