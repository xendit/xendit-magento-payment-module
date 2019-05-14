<?php
namespace Xendit\M2Invoice\Model\Adminhtml\Source;

/**
 * Class EnvRadioBtn
 */
class EnvRadioBtn implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * {@inheritdoc}
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
