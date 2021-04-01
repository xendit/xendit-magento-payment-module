<?php

namespace Xendit\M2Invoice\Model\Adminhtml\Source;

use Magento\Framework\Option\ArrayInterface;

/**
 * Class AllowedMethod
 * @package Xendit\M2Invoice\Model\Adminhtml\Source
 */
class AllowedMethod implements ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'all', 'label' => __('All available payment method on Xendit')],
            ['value' => 'specific', 'label' => __('Specific payment method on Xendit')]
        ];
    }
}
