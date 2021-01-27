<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Model\Order;
use Xendit\M2Invoice\Enum\LogDNALevel;

class ProcessHosted extends AbstractAction
{
    public function execute()
    {
        try {
            $shouldRedirect = true;

            $order = $this->getOrder();
            $payment = $order->getPayment();

            if ($payment->getAdditionalInformation('xendit_hosted_payment_id') !== null) {
                $requestData = [
                    'id' => $payment->getAdditionalInformation('xendit_hosted_payment_id'),
                    'hp_token' => $payment->getAdditionalInformation('xendit_hosted_payment_token')
                ];

                $hostedPayment = $this->getCompletedHostedPayment($requestData);

                if (isset($hostedPayment['error_code'])) {
                    return $this->handlePaymentFailure($order, $hostedPayment['error_code'], 'Error reconciliating', $shouldRedirect);
                }
                if ($hostedPayment['paid_amount'] != $hostedPayment['amount']) {
                    $order->setBaseDiscountAmount($hostedPayment['paid_amount'] - $hostedPayment['amount']);
                    $order->setDiscountAmount($hostedPayment['paid_amount'] - $hostedPayment['amount']);
                    $order->save();
    
                    $order->setBaseGrandTotal($order->getBaseGrandTotal() + $order->getBaseDiscountAmount());
                    $order->setGrandTotal($order->getGrandTotal() + $order->getDiscountAmount());
                    $order->save();
                }
                $payment->setAdditionalInformation('token_id', $hostedPayment['token_id']);
                $payment->setAdditionalInformation('xendit_installment', $hostedPayment['installment']);
                return $this->processSuccessfulTransaction(
                    $order,
                    $payment,
                    'Xendit Credit Card payment completed. Transaction ID: ',
                    $hostedPayment['charge_id'],
                    $shouldRedirect
                );
            }
            
            $message = 'No action on xendit/checkout/redirect';
            return $this->handlePaymentFailure($order, $message, 'No payment recorded');
        } catch (\Exception $e) {
            $message = 'Exception caught on xendit/checkout/redirect: ' . $e->getMessage();
            return $this->handlePaymentFailure($order, $message, 'Unexpected error');
        }
    }

    private function processSuccessfulTransaction($order, $payment, $paymentMessage, $transactionId, $shouldRedirect = true)
    {
        $orderState = Order::STATE_PROCESSING;
        $order->setState($orderState)
            ->setStatus($orderState)
            ->addStatusHistoryComment("$paymentMessage $transactionId");

        $order->save();

        $payment->setTransactionId($transactionId);
        $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE, null, true);

        $this->invoiceOrder($order, $transactionId);

        $this->getMessageManager()->addSuccessMessage(__("Your payment with Xendit is completed"));

        if ($shouldRedirect) {
            return $this->_redirect('checkout/onepage/success', [ '_secure'=> false ]);
        }
    }

    private function getCompletedHostedPayment($requestData)
    {
        $url = $this->getDataHelper()->getCheckoutUrl() . "/payment/xendit/hosted-payments/" . $requestData['id'] . "?hp_token=" . $requestData['hp_token'] . '&statuses[]=COMPLETED';
        $method = \Zend\Http\Request::METHOD_GET;

        try {
            $hostedPayment = $this->getApiHelper()->request(
                $url, $method
            );
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            throw new \Magento\Framework\Exception\LocalizedException(
                new Phrase($e->getMessage())
            );
        }

        return $hostedPayment;
    }

    public function handlePaymentFailure($order, $message, $reason, $shouldRedirect = true)
    {
        $this->getLogDNA()->log(LogDNALevel::ERROR, $message);

        $this->cancelOrder($order, $reason);

        if ($shouldRedirect) {
            return $this->redirectToCart($message);
        } else {
            $this->getMessageManager()->addErrorMessage(__(
                "There was an error in the Xendit payment. Failure reason: $reason"
            ));
        }
    }
}
