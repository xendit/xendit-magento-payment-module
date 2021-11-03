<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;

/**
 * Class Redirect
 * @package Xendit\M2Invoice\Controller\Checkout
 */
class Redirect extends AbstractAction
{
    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        try {
            $order = $this->getOrder();
            $orderId = $order->getRealOrderId();
            $payment = $order->getPayment();
            $this->getMessageManager()->getMessages(true);

            $orderState = Order::STATE_PENDING_PAYMENT;
            $order->setState($orderState)
                ->setStatus($orderState);
            $order->save();

            if ($payment->getAdditionalInformation('xendit_failure_reason') !== null) {
                $failureReason = $payment->getAdditionalInformation('xendit_failure_reason');
                $this->cancelOrder($order, $failureReason);
                return $this->redirectToCart($failureReason);
            }
            // CC Subscription
            if ($payment->getAdditionalInformation('xendit_redirect_url') !== null) {
                $redirectUrl = $payment->getAdditionalInformation('xendit_redirect_url');

                $resultRedirect = $this->getRedirectFactory()->create();
                $resultRedirect->setUrl($redirectUrl);
                return $resultRedirect;
            }
            // Qrcode
            if ($payment->getAdditionalInformation('xendit_qrcode_external_id') !== null) {
                $args  = [
                    '_secure'=> true,
                    'xendit_qrcode_external_id'     => $payment->getAdditionalInformation('xendit_qrcode_external_id'),
                    'xendit_qr_string'              => $payment->getAdditionalInformation('xendit_qr_string'),
                    'xendit_qrcode_type'            => $payment->getAdditionalInformation('xendit_qrcode_type'),
                    'xendit_qrcode_status'          => $payment->getAdditionalInformation('xendit_qrcode_status'),
                    'xendit_qrcode_amount'          => $payment->getAdditionalInformation('xendit_qrcode_amount'),
                    'xendit_qrcode_is_multishipping'=> $payment->getAdditionalInformation('xendit_qrcode_is_multishipping')
                ];

                $urlData = [
                    'data' => base64_encode(json_encode($args))
                ];

                $resultRedirect = $this->getRedirectFactory()->create();
                $resultRedirect->setPath('xendit/checkout/qrcode', $urlData);
                return $resultRedirect;
            }

            $this->cancelOrder($order, 'No payment recorded');

            return $this->redirectToCart("There was an error in the Xendit payment. Failure reason: Unexpected Error");

        } catch (\Exception $e) {
            $this->cancelOrder($order, $e->getMessage());

            return $this->redirectToCart("There was an error in the Xendit payment. Failure reason: Unexpected Error");
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
}
