<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Xendit\M2Invoice\Model\Payment\QRCODES;
use Xendit\M2Invoice\Helper\Data as XenditHelper;
use Magento\Framework\Webapi\Exception;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\OrderFactory;

class QrcodeSimulate extends \Magento\Framework\App\Action\Action
{
    /**
     * @var QRCODES
     */
    protected $qrcodeModel;

    /**
     * @var XenditHelper
     */
    protected $xenditHelper;

    /**
     * @var \Magento\Framework\Controller\Result\RedirectFactory
     */
    protected $resultRedirectFactory;

    /**
     * QrcodeSimulate constructor.
     * @param QRCODES $qrcodeModel
     * @param XenditHelper $xenditHelper
     * @param Context $context
     */
    public function __construct(
        QRCODES $qrcodeModel,
        XenditHelper $xenditHelper,
        Context $context
    ) {
        $this->qrcodeModel = $qrcodeModel;
        $this->xenditHelper = $xenditHelper;
        $this->resultRedirectFactory = $context->getResultRedirectFactory();
        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|void
     * @throws LocalizedException
     */
    public function execute()
    {
        $postData = $this->getRequest()->getPostValue();
        $isMultishipping = isset($postData['isMultishipping']) ? (int) $postData['isMultishipping'] : 0;

        $args = [
            'external_id' => $postData['externalId'],
            'amount'      => $postData['amount']
        ];

        try {
            // simulate Qrcode Payment request
            $paymentResponse = $this->qrcodeModel->simulateQrcodePayment($args);

            // handle '422' error
            if ( isset($paymentResponse['error_code']) ) {
                $message = $this->mapQrcodeErrorCode($paymentResponse['error_code']);
                $this->messageManager->addErrorMessage(__("Exception occurred while stimulating Qrcode payment: " . $message));
                $this->_redirect('checkout/cart');
            }
            // $paymentResponse['status'] == 'COMPLETED'
            if ( $paymentResponse['qr_code'] && $paymentResponse['qr_code']['external_id']) {
                    $orderIdList = explode('-', $paymentResponse['qr_code']['external_id']);
                    $orderIds = [];
                    foreach ($orderIdList as $orderId) {
                        if (is_numeric($orderId)) {
                            $orderIds[] = $orderId;
                        }
                    }
                    // multi-shipping
                    if ($isMultishipping) {
                        foreach ($orderIdList as $orderId) {
                            if (is_numeric($orderId)) {
                                $order = $this->qrcodeModel->getOrderById($orderId);
                                $result = $this->qrcodeModel->checkOrder($order, $paymentResponse);
                            }
                        }
                    } else {
                        // onepage-checkout
                        $order = $this->qrcodeModel->getOrderById((int)$orderIds[0]);
                        // TODO: if order is not found
                        $result = $this->qrcodeModel->checkOrder($order, $paymentResponse);


                    }

                    if ($result['status_code'] == 'success') {
                        $redirectUrl = $this->xenditHelper->getSuccessUrl($isMultishipping);
                    } else {
                        $redirectUrl = $this->xenditHelper->getFailureUrl((int)$orderIds[0], $isMultishipping);
                    }
                    $resultRedirect = $this->resultRedirectFactory->create();
                    $resultRedirect->setUrl($redirectUrl);
                    return $resultRedirect;
            }

        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            throw new LocalizedException(
                new Phrase($errorMsg)
            );
        }
    }

    /**
     * @param $errorCode
     * @return string
     */
    private function mapQrcodeErrorCode($errorCode)
    {
        switch ( $errorCode ) {
            case 'INACTIVE_QR_CODE':
                return 'Payment simulation for DYNAMIC QRIS has been completed previously. DYNAMIC QRIS is inactive.';
            case 'DATA_NOT_FOUND':
                return 'The QR code with specified external_id does not exist.';
            case 'REQUEST_FORBIDDEN_ERROR':
                return 'API key in use does not have necessary permissions to perform the request. 
                        Please assign proper permissions for the key';
            case 'API_VALIDATION_ERROR':
                return 'There is invalid input in one of the required request fields.';
            default:
                return "Failed to pay with QRIS. Error code: $errorCode";
        }
    }
}