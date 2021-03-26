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

namespace Xendit\M2Invoice\Block\Checkout;

use Magento\Framework\View\Element\Template;
use Xendit\M2Invoice\Helper\Data;

class Qrcode extends Template
{
    /**
     * @var Data
     */
    protected $dataHelper;

    /**
     * Qrcode constructor.
     * @param Data $dataHelper
     * @param Template\Context $context
     * @param array $data
     */
    public function __construct(
        Data $dataHelper,
        Template\Context $context,
        array $data = []
    ) {
        $this->dataHelper = $dataHelper;
        parent::__construct($context, $data);
    }

    /**
     * @return bool
     */
    public function isTestEnvironment()
    {
        $status = false;
        $environment = $this->dataHelper->getEnvironment();
        if ($environment == "test") {
            $status = true;
        }
        return $status;
    }

    /**
     * Returns action url for contact form
     *
     * @return string
     */
    public function getQrcodeSimulateAction()
    {
        return $this->getUrl('xendit/checkout/qrcodesimulate', ['_secure' => true]);
    }

}