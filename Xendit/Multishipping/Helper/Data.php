<?php
namespace Xendit\Multishipping\Helper;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    public function xenditPaymentMethod( $payment ){
        
        //methode name => frontend routing
        $listPayment = [
            "bcava" => "bcava",
            "bniva" => "bniva",
            "briva" => "briva",
            "mandiriva" => "mandiriva",
            "permatava" => "permatava",
            "alfamart" => "alfamart"
        ];

        $response = FALSE;
        if( !!array_key_exists($payment, $listPayment) ){
            $response = $listPayment[$payment];
        }

        return $response; 
    }
}