<?php

namespace Xendit\M2Invoice\Model\Adminhtml\Source;

/**
 * @since 1.4.1
 */
class SubscriptionInterval implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'MONTH', 'label' => __('Month')],
            ['value' => 'WEEK', 'label' => __('Week')],
            ['value' => 'DAY', 'label' => __('Day')]
        ];
    }
}
