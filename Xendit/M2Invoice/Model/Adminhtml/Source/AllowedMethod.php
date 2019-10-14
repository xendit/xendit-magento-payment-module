<?php

namespace Xendit\M2Invoice\Model\Adminhtml\Source;

/**
 * @since 1.4.1
 */
class AllowedMethod implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'all', 'label' => __('All available payment method on Xendit')],
            ['value' => 'specific', 'label' => __('Specific payment method on Xendit')]
        ];
    }
}
