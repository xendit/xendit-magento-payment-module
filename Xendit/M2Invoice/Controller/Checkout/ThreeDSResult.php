<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Sales\Model\Order;
use Xendit\M2Invoice\Enum\LogDNALevel;

class ThreeDSResult extends AbstractAction
{
    public function execute()
    {
        $orderId = $this->getRequest()->get('order_id');
        $hosted3DSId = $this->getRequest()->get('hosted_3ds_id');

        $order = $this->getOrderById($orderId);

        if (!is_object($order)) {
            return;
        }

        if ($order->getState() !== Order::STATE_PAYMENT_REVIEW) {
            return;
        }

        try {
            $hosted3DS = $this->getThreeDSResult($hosted3DSId);

            if ('VERIFIED' !== $hosted3DS['status']) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('Authentication process failed. Please try again.')
                );
            }

            $charge = $this->createCharge($hosted3DS, $orderId);

            $chargeError = isset($charge['error_code']) ? $charge['error_code'] : null;
            if ( $chargeError == 'EXTERNAL_ID_ALREADY_USED_ERROR' ) {
                $charge = $this->createCharge($hosted3DS, $orderId, true);
            }

            return $this->processXenditPayment($charge, $order);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $message = 'Exception caught on xendit/checkout/threedsresult: ' . $e->getMessage();
            $this->getLogDNA()->log(LogDNALevel::ERROR, $message);
            return $this->processFailedPayment($order);
        }
    }

    private function getThreeDSResult($hosted3DSId)
    {
        $hosted3DSUrl = $this->getDataHelper()->getCheckoutUrl() 
            . "/payment/xendit/credit-card/hosted-3ds/$hosted3DSId";
        $hosted3DSMethod = \Zend\Http\Request::METHOD_GET;
        
        try {
            $hosted3DS = $this->getApiHelper()->request($hosted3DSUrl, $hosted3DSMethod, null, true);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Failed to retrieve hosted 3DS data')
            );
        }

        return $hosted3DS;
    }

    private function createCharge($hosted3DS, $orderId, $duplicate = false)
    {
        $chargeUrl = $this->getDataHelper()->getCheckoutUrl() . "/payment/xendit/credit-card/charges";
        $chargeMethod = \Zend\Http\Request::METHOD_POST;
        $originalExternalId = $this->getDataHelper()->getExternalId($orderId);
        $duplicateExternalId = $this->getDataHelper()->getExternalId($orderId, true);
        $chargeData = [
            'token_id' => $hosted3DS['token_id'],
            'authentication_id' => $hosted3DS['authentication_id'],
            'amount' => $hosted3DS['amount'],
            'external_id' => $duplicate ? $duplicateExternalId : $originalExternalId
        ];

        try {
            $charge = $this->getApiHelper()->request($chargeUrl, $chargeMethod, $chargeData, false);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Failed to create charge')
            );
        }

        return $charge;
    }

    private function processXenditPayment($charge, $order)
    {
        if ($charge['status'] === 'CAPTURED') {
            $orderState = Order::STATE_PROCESSING;
            $transactionId = $charge['id'];

            $order->setState($orderState)
                ->setStatus($orderState)
                ->addStatusHistoryComment("Xendit payment completed. Transaction ID: $transactionId");
            
                $payment = $order->getPayment();
                $payment->setTransactionId($transactionId);
                $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE, null, true);

            $order->save();

            $this->invoiceOrder($order, $transactionId);

            $this->getMessageManager()->addSuccessMessage(__("Your payment with Xendit is completed"));
            $this->_redirect('checkout/onepage/success', [ '_secure'=> false ]);
        } else {
            $this->processFailedPayment($order, $charge);
        }
    }

    private function processFailedPayment($order, $charge = [])
    {
        $this->getCheckoutHelper()->cancelOrderById($order->getId(),
            "Order #".($order->getId())." was rejected by Xendit");
        $this->getCheckoutHelper()->restoreQuote(); //restore cart

        if ($charge === []) {
            $failureReason = 'Unexpected Error';
        } else {
            $failureReason = isset($charge['failure_reason']) ? $charge['failure_reason'] : 'Unexpected Error';
        }

        $orderState = Order::STATE_CANCELED;
        $order->setState($orderState)
            ->setStatus($orderState)
            ->addStatusHistoryComment("Order #" . $order->getId() . " was rejected by Xendit because " .
                $failureReason);
        $order->save();

        $failureReasonInsight = $this->getDataHelper()->failureReasonInsight($failureReason);
        $this->getMessageManager()->addErrorMessage(__(
            "$failureReason - $failureReasonInsight"
        ));
        $this->_redirect('checkout/cart', array('_secure'=> false));
    }
}
