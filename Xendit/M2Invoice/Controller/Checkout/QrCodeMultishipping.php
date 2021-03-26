<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Model\Order;
use Zend\Http\Request;

/**
 * Class QrCodeMultishipping
 * @package Xendit\M2Invoice\Controller\Checkout
 */
class QrCodeMultishipping extends AbstractAction
{
    protected $type = 'DYNAMIC';

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Redirect|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        try {
            $rawOrderIds        = $this->getRequest()->getParam('order_ids');
            $orderIds           = explode("-", $rawOrderIds);
            $transactionAmount  = 0;
            $orderProcessed     = false;
            $orders             = [];
            $orderIncrementIds  = '';
            $c                  = 0;

            foreach ($orderIds as $key => $value) {
                $order = $this->getOrderFactory()->create();
                $order->load($value);
                if ($c>0) {
                    $orderIncrementIds .= "-";
                }
                $orderIncrementIds .= $order->getRealOrderId();
                $orderState = $order->getState();
                if ($orderState === Order::STATE_PROCESSING && !$order->canInvoice()) {
                    $orderProcessed = true;
                    continue;
                }
                $order->setState(Order::STATE_PENDING_PAYMENT)
                      ->setStatus(Order::STATE_PENDING_PAYMENT)
                      ->addStatusHistoryComment("Pending Xendit payment.");

                array_push($orders, $order);
                $order->save();
                $transactionAmount  += (int)$order->getTotalDue();
                $c++;
            }
            if ($orderProcessed) {
                return $this->_redirect('multishipping/checkout/success');
            }

            $args = [
                'external_id'   => $this->getDataHelper()->getExternalId($orderIncrementIds),
                'type'          => $this->type,
                'description'   => $orderIncrementIds,
                'callback_url'  => $this->getXenditCallbackUrl(),
                'amount'        => round($transactionAmount)
            ];

            // send Qrcode Payment request
            $qrcodePayment = $this->requestQrcodePayment($args);

            if (isset($qrcodePayment['error_code'])) {
                $message = $this->mapQrcodeErrorCode($qrcodePayment['error_code']);
                return $this->processFailedPayment($orderIds, $message);
            }

            $data  = [
                '_secure'=> true,
            ];

            if (isset($qrcodePayment['external_id'])) {
                $data['xendit_qrcode_external_id'] = $qrcodePayment['external_id'];
            }
            if (isset($qrcodePayment['qr_string'])) {
                $data['xendit_qr_string'] = $qrcodePayment['qr_string'];
            }
            if (isset($qrcodePayment['type'])) {
                $data['xendit_qrcode_type'] = $qrcodePayment['type'];
            }
            if (isset($qrcodePayment['status'])) {
                $data['xendit_qrcode_status'] = $qrcodePayment['status'];
            }
            // set amount
            $data['xendit_qrcode_amount'] = round($transactionAmount);
            // set is_multishipping
            $data['xendit_qrcode_is_multishipping'] = true;

            $urlData = [
                'data' => base64_encode(json_encode($data))
            ];

            $resultRedirect = $this->getRedirectFactory()->create();
            $resultRedirect->setPath('xendit/checkout/qrcode', $urlData);
            return $resultRedirect;

        } catch (\Exception $e) {
            $message = 'Exception caught on xendit/checkout/redirect: ' . $e->getMessage();
            $this->getLogger()->info($message);
            return $this->redirectToCart($message);
        }
    }

    /**
     * @param $failureReason
     * @return \Magento\Framework\Controller\ResultInterface
     */
    private function redirectToCart($failureReason)
    {
        $failureReasonInsight = $this->getDataHelper()->failureReasonInsight($failureReason);
        $this->getMessageManager()->addErrorMessage(__(
            $failureReasonInsight
        ));
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($this->_url->getUrl('checkout/cart'), [ '_secure'=> false ]);
        return $resultRedirect;
    }

    /**
     * @param $requestData
     * @return mixed
     * @throws \Exception
     */
    private function requestQrcodePayment($requestData)
    {
        $this->logger->info(json_encode($requestData));

        $qrocodeUrl = $this->getDataHelper()->getCheckoutUrl() . "/qr_codes";
        $qrcodeMethod = Request::METHOD_POST;
        $options = [
            'timeout' => 60
        ];

        try {
            $qrcodePayment = $this->getApiHelper()->request(
                $qrocodeUrl,
                $qrcodeMethod,
                $requestData,
                false,
                null,
                $options,
                [
                    'x-api-version' => '2020-02-01'
                ]
            );
            $this->logger->info(json_encode($qrcodePayment));
        } catch (\Exception $e) {
            $this->logger->info(json_encode($e));
            throw $e;
        }

        return $qrcodePayment;
    }

    /**
     * @param $errorCode
     * @return string
     */
    private function mapQrcodeErrorCode($errorCode)
    {
        switch ( $errorCode ) {
            case 'DUPLICATE_ERROR':
                return 'The payment with the same external_id has already been made before.';
            case 'DATA_NOT_FOUND':
                return 'QRIS merchant not found, please contact our customer success team for activation.';
            case 'REQUEST_FORBIDDEN_ERROR':
                return 'API key in use does not have necessary permissions to perform the request. 
                        Please assign proper permissions for the key';
            case 'API_VALIDATION_ERROR':
                return 'There is invalid input in one of the required request fields.';
            default:
                return "Failed to pay with QRIS. Error code: $errorCode";
        }
    }

    /**
     * $orderIds = prefixless order IDs
     * @param $orderIds
     * @param string $failureReason
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function processFailedPayment($orderIds, $failureReason = 'Unexpected Error with empty charge')
    {
        $this->getCheckoutHelper()->processOrdersFailedPayment($orderIds, $failureReason);

        $failureReasonInsight = $this->getDataHelper()->failureReasonInsight($failureReason);
        $this->getMessageManager()->addErrorMessage(__(
            $failureReasonInsight
        ));
        $this->_redirect('checkout/cart', ['_secure'=> false]);
    }
}
