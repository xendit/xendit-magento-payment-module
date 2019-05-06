<?php

namespace Xendit\M2Invoice\Block;

use Magento\Payment\Block\ConfigurableInfo;

class Info extends ConfigurableInfo
{
    protected function getLabel($field)
    {
        return __($field);
    }
}