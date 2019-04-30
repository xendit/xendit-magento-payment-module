<?php

namespace Xendit\M2Invoice\Model\Payment;

// use Magento\Payment\Model\CcGenericConfigProvider;

class CC extends \Magento\Payment\Model\Method\Cc
{
    const CODE = 'CC';
    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'cc';

    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        //todo add functionality later
    }
 
    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        //todo add functionality later
    }
}