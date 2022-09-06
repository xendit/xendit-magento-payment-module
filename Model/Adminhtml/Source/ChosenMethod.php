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
            ['value' => 'bjbva', 'label' => __('Bank Transfer BJB')],
            ['value' => 'bniva', 'label' => __('Bank Transfer BNI')],
            ['value' => 'briva', 'label' => __('Bank Transfer BRI')],
            ['value' => 'bsiva', 'label' => __('Bank Transfer BSI')],
            ['value' => 'mandiriva', 'label' => __('Bank Transfer Mandiri')],
            ['value' => 'permatava', 'label' => __('Bank Transfer Permata')],
            ['value' => 'cc', 'label' => __('Credit Card')],
            ['value' => 'dana', 'label' => __('DANA')],
            ['value' => 'indomaret', 'label' => __('Indomaret')],
            ['value' => 'ovo', 'label' => __('OVO')],
            ['value' => 'shopeepay', 'label' => __('ShopeePay')],
            ['value' => 'linkaja', 'label' => __('LinkAja')],
            ['value' => 'qris', 'label' => __('QRIS')],
            ['value' => 'dd_bri', 'label' => __('Direct Debit (BRI)')],
            ['value' => 'kredivo', 'label' => __('Kredivo')],
            ['value' => 'gcash', 'label' => __('GCash')],
            ['value' => 'grabpay', 'label' => __('GrabPay')],
            ['value' => 'paymaya', 'label' => __('PayMaya')],
            ['value' => 'dd_bpi', 'label' => __('Direct Debit (BPI)')],
            ['value' => 'seven_eleven', 'label' => __('7-Eleven')],
            ['value' => 'dd_ubp', 'label' => __('Direct Debit (UBP)')],
            ['value' => 'billease', 'label' => __('BillEase')],
            ['value' => 'cebuana', 'label' => __('Cebuana')],
            ['value' => 'dp_palawan', 'label' => __('Palawan Express Pera Padala')],
            ['value' => 'dp_mlhuillier', 'label' => __('M Lhuillier')],
            ['value' => 'dp_ecpay_loan', 'label' => __('ECPay Loans')],
            ['value' => 'cashalo', 'label' => __('Cashalo')],
            ['value' => 'shopeepayph', 'label' => __('ShopeePay')],
            ['value' => 'uangme', 'label' => __('Uangme')],
            ['value' => 'astrapay', 'label' => __('AstraPay')],
            ['value' => 'akulaku', 'label' => __('Akulaku')],
            ['value' => 'dd_rcbc', 'label' => __('Direct Debit (RCBC)')],
        ];

        if (!$isMultiselect) {
            array_unshift($options, ['value' => '', 'label' => __('--Please Select--')]);
        }
        return $options;
    }
}
