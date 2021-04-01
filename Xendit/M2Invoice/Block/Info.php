<?php

namespace Xendit\M2Invoice\Block;

use Magento\Payment\Block\ConfigurableInfo;

/**
 * Class Info
 * @package Xendit\M2Invoice\Block
 */
class Info extends ConfigurableInfo
{
    /**
     * @param string $field
     * @return \Magento\Framework\Phrase|string
     */
    protected function getLabel($field)
    {
        return $field;
    }
}
