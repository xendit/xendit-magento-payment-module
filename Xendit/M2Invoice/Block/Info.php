<?php

namespace Xendit\M2Invoice\Block;

use Magento\Payment\Block\ConfigurableInfo;

class Info extends ConfigurableInfo
{
    private function getLabel($field)
    {
        return __($field);
    }
}
