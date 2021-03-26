<?php

namespace Xendit\M2Invoice\Model\Adminhtml\Source;

use Magento\Framework\Option\ArrayInterface;

/**
 * Class SubscriptionInterval
 * @package Xendit\M2Invoice\Model\Adminhtml\Source
 */
class SubscriptionInterval implements ArrayInterface
{
    /**
     * @return array
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
