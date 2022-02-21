<?php

namespace Xendit\M2Invoice\Model\Adminhtml\Source;

use Magento\Framework\Option\ArrayInterface;

/**
 * Class CardImages
 * @package Xendit\M2Invoice\Model\Adminhtml\Source
 */
class CardImages implements ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'VI', 'label' => __('Visa')],
            ['value' => 'MC', 'label' => __('Mastercard')],
            ['value' => 'AE', 'label' => __('AMEX')],
            ['value' => 'JCB', 'label' => __('JCB')],
        ];
    }
}
