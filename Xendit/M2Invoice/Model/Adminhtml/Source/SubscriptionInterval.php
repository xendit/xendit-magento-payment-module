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
            ['value' => 'MONTH', 'label' => __('MONTH')],
            ['value' => 'WEEK', 'label' => __('WEEK')],
            ['value' => 'DAY', 'label' => __('DAY')]
        ];
    }
}
