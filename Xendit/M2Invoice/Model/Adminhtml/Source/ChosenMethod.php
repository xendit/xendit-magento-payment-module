<?php

namespace Xendit\M2Invoice\Model\Adminhtml\Source;

/**
 * Options provider for countries list
 *
 * @api
 * @since 100.0.2
 */
class ChosenMethod implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Return options array
     *
     * @param boolean $isMultiselect
     * @return array
     */
    public function toOptionArray($isMultiselect = false)
    {
        $options = [
            ['value' => 'alfamart', 'label' => __('Alfamart')],
            ['value' => 'bcava', 'label' => __('Bank Transfer BCA')],
            ['value' => 'bniva', 'label' => __('Bank Transfer BNI')],
            ['value' => 'briva', 'label' => __('Bank Transfer BRI')],
            ['value' => 'mandiriva', 'label' => __('Bank Transfer Mandiri')],
            ['value' => 'permatava', 'label' => __('Bank Transfer Permata')],
            ['value' => 'cc', 'label' => __('Credit Card')],
            ['value' => 'cc_installment', 'label' => __('Credit Card Installment')],
            ['value' => 'cc_subscription', 'label' => __('Credit Card Subscription')],
            ['value' => 'ovo', 'label' => __('OVO')],
        ];

        if (!$isMultiselect) {
            array_unshift($options, ['value' => '', 'label' => __('--Please Select--')]);
        }
        return $options;
    }
}