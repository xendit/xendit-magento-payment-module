<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Xendit\M2Invoice\Model\Payment\QRCODES;

class QrcodeCheckStatus extends \Magento\Framework\App\Action\Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var QRCODES
     */
    protected $qrcodeModel;

    /**
     * QrcodeCheckStatus constructor.
     * @param JsonFactory $resultJsonFactory
     * @param QRCODES $qrcodeModel
     * @param Context $context
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        QRCODES $qrcodeModel,
        Context $context
    ) {
        $this->resultJsonFactory    = $resultJsonFactory;
        $this->qrcodeModel          = $qrcodeModel;
        parent::__construct($context);
    }

    public function execute()
    {
        $externalId = ($this->getRequest()->getParam('externalId')) ? $this->getRequest()->getParam('externalId') : '' ;
        $args = [
          'externalId' => $externalId
        ];
        $result = $this->resultJsonFactory->create();
        $response = $this->qrcodeModel->checkQrCodeStatus($args);
        $result->setData($response);
        return $result;
    }
}