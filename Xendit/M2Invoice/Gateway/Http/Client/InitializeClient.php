<?php

namespace Xendit\M2Invoice\Gateway\Http\Client;

use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;

class InitializeClient implements ClientInterface
{
    public function placeRequest(TransferInterface $transferObject)
    {
        $response = [ 'IGNORED' => [ 'IGNORED' ] ];

        return $response;
    }
}
