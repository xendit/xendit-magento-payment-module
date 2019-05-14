<?php

namespace Xendit\M2Invoice\Gateway\Http;

use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Http\TransferBuilder;
use Magento\Payment\Gateway\Http\TransferInterface;

class XenditTransferFactoryInterface implements TransferFactoryInterface
{
    private $transferBuilder;

    public function __construct(
        TransferBuilder $transferBuilder
    ) {
        $this->transferBuilder = $transferBuilder;
    }

    public function create(array $request)
    {
        return $this->transferBuilder
            ->setBody($request)
            ->setMethod('POST')
            ->build();
    }
}
