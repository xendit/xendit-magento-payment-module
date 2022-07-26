<?php

namespace Xendit\M2Invoice\Gateway\Http\Client;

use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;

/**
 * Class InitializeClient
 * @package Xendit\M2Invoice\Gateway\Http\Client
 */
class InitializeClient implements ClientInterface
{
    /**
     * @param TransferInterface $transferObject
     * @return array
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $response = [ 'IGNORED' => [ 'IGNORED' ] ];
        return $response;
    }
}
