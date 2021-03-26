<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Xendit\M2Invoice\Helper\Qrcode as QrcodeHelper;
use Magento\Framework\Exception\LocalizedException;

class Qrcode extends \Magento\Framework\App\Action\Action
{
    /**
     * @var QrcodeHelper
     */
    protected $qrcodeHelper;

    /**
     * Qrcode constructor.
     * @param Context $context
     * @param QrcodeHelper $qrcodeHelper
     */
    public function __construct(
        Context $context,
        QrcodeHelper $qrcodeHelper
    ) {
        $this->qrcodeHelper     = $qrcodeHelper;
        parent::__construct($context);
    }

    /**
     * @return Page|\Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $params             = $this->getRequest()->getParams();
        $data               = json_decode(base64_decode($this->getRequest()->getParam('data')),true);
        $externalId         = (isset($data['xendit_qrcode_external_id'])) ? $data['xendit_qrcode_external_id'] : '';
        $qrString           = (isset($data['xendit_qr_string'])) ? $data['xendit_qr_string'] : '';
        $amount             = (isset($data['xendit_qrcode_amount'])) ? $data['xendit_qrcode_amount'] : '';
        $isMultishipping    = (isset($data['xendit_qrcode_is_multishipping'])) ? $data['xendit_qrcode_is_multishipping'] : false;
        $qrcodeUrl          = "";

        /** @var Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        /** @var Template $block */
        $block = $page->getLayout()->getBlock('xendit_qrcode_block');
        if ($params) {
            if ($qrString && $externalId) {
                try {
                    // generate QRcode Image from QR string
                    $qrcodeUrl = $this->qrcodeHelper->generateQrcode($qrString, $externalId);
                } catch (LocalizedException $e) {
                    $this->messageManager->addErrorMessage(__("Exception occurred while generating Qrcode"));
                    $this->_redirect('checkout/cart');
                }
                $block->setData('xendit_qrcode_external_id', $externalId);
                $block->setData('xendit_qrcode_amount', $amount);
                $block->setData('xendit_qrcode_path', $qrcodeUrl);
                $block->setData('xendit_qrcode_is_multishipping', $isMultishipping);
            }
        }
        return $page;
    }
}