<?php

namespace Xendit\M2Invoice\Model\Adminhtml\Source;

use Magento\Framework\Option\ArrayInterface;

/**
 * Class CardPaymentType
 * @package Xendit\M2Invoice\Model\Adminhtml\Source
 */
class CardPaymentType implements ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => 'form',
                'label' => __('Card Form')
            ],
            [
                'value' => 'popup',
                'label' => __('Pop Up')
            ],
        ];
    }
}
