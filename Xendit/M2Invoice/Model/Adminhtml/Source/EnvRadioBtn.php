<?php

namespace Xendit\M2Invoice\Model\Adminhtml\Source;

use Magento\Framework\Option\ArrayInterface;

/**
 * Class EnvRadioBtn
 * @package Xendit\M2Invoice\Model\Adminhtml\Source
 */
class EnvRadioBtn implements ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => 'test',
                'label' => __('Test')
            ],
            [
                'value' => 'live',
                'label' => __('Live')
            ],
        ];
    }
}
