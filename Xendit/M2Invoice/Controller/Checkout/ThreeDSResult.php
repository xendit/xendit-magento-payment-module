<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Sales\Model\Order;

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

        if ($order->getState() !== Order::STATE_PENDING_PAYMENT) {
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

            return $this->processXenditPayment($charge, $order);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            return $this->processFailedPayment($order);
        }
    }

    private function getThreeDSResult($hosted3DSId)
    {
        $hosted3DSUrl = $this->getDataHelper()->getCheckoutUrl() . "/payment/xendit/credit-card/hosted-3ds/$hosted3DSId";
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

    private function createCharge($hosted3DS, $orderId)
    {
        $chargeUrl = $this->getDataHelper()->getCheckoutUrl() . "/payment/xendit/credit-card/charge";
        $chargeMethod = \Zend\Http\Request::METHOD_POST;
        $chargeData = array(
            'token_id' => $hosted3DS['token_id'],
            'authentication_id' => $hosted3DS['authentication_id'],
            'amount' => $hosted3DS['amount'],
            'external_id' => $this->getDataHelper()->getExternalId($orderId)
        );

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
        if ($charge['status'] === 'CHARGED') {
            $orderState = Order::STATE_PROCESSING;

            $order->setState($orderState)
                ->setStatus($orderState)
                ->addStatusHistoryComment("Xendit payment completed. Transaction ID: $transactionId");
            
                $payment = $order->getPayment();
                $payment->setTransactionId($transactionId);
                $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE, null, true);

            $order->save();

            $this->invoiceOrder($order, $transactionId);

            $this->getMessageManager()->addSuccessMessage(__("Your payment with Xendit is completed"));
            $this->_redirect('checkout/onepage/success', array('_secure'=> false));
        } else {
            $this->processFailedPayment($order);
        }
    }

    private function processFailedPayment($order)
    {
        $this->getCheckoutHelper()->cancelOrderById($order->getId(), "Order #".($order->getId())." was rejected by Xendit");
        $this->getCheckoutHelper()->restoreQuote(); //restore cart

        $this->getMessageManager()->addErrorMessage(__("There was an error in the Xendit payment"));
        $this->_redirect('checkout/cart', array('_secure'=> false));
    }
}