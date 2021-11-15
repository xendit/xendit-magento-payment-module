<?php

namespace Xendit\M2Invoice\Model\Adminhtml\Source;

use Magento\Framework\Option\ArrayInterface;

/**
 * Options provider for countries list
 * Class ChosenMethod
 * @package Xendit\M2Invoice\Model\Adminhtml\Source
 */
class ChosenMethod implements ArrayInterface
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
            ['value' => 'cc_subscription', 'label' => __('Credit Card Subscription')],
            ['value' => 'dana', 'label' => __('DANA')],
            ['value' => 'indomaret', 'label' => __('Indomaret')],
            ['value' => 'ovo', 'label' => __('OVO')],
            ['value' => 'shopeepay', 'label' => __('ShopeePay')],
            ['value' => 'linkaja', 'label' => __('LinkAja')],
            ['value' => 'qris', 'label' => __('QRIS')],
            ['value' => 'dd_bri', 'label' => __('Direct Debit (BRI)')],
            ['value' => 'kredivo', 'label' => __('Kredivo')],
        ];

        if (!$isMultiselect) {
            array_unshift($options, ['value' => '', 'label' => __('--Please Select--')]);
        }
        return $options;
    }
}