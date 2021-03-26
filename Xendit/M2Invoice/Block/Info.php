<?php
/**
 * Copyright © 2020 PT Kemana Teknologi Solusi. All rights reserved.
 * http://www.kemana.com
 */

/**
 * @category Kemana
 * @package  Kemana_Xendit
 * @license  http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 *
 * @author   Anton Vinoj <avinoj@kemana.com>
 */

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
