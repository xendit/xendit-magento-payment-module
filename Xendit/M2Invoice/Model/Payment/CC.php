<?php

namespace Xendit\M2Invoice\Model\Payment;

// use Magento\Payment\Model\CcGenericConfigProvider;

class CC extends \Magento\Payment\Model\Method\Cc
{
    const CODE = 'cc';
    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = self::CODE;

    protected $_isGateway = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;

    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        //todo add functionality later
    }
 
    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        echo 'something';
    }
}