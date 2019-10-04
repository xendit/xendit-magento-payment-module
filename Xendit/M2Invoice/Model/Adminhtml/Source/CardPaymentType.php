<?php
namespace Xendit\M2Invoice\Model\Adminhtml\Source;

/**
 * Class EnvRadioBtn
 */
class CardPaymentType implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * {@inheritdoc}
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
