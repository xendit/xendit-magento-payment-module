<?php
namespace Xendit\Multishipping\Helper;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    public function xenditPaymentMethod( $payment ){
        
        //methode name => frontend routing
        $listPayment = [
            "cc" => "cc"
        ];

        $response = FALSE;
        if( !!array_key_exists($payment, $listPayment) ){
            $response = $listPayment[$payment];
        }

        return $response; 
    }
}