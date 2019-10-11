<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Model\Order;
use Xendit\M2Invoice\Enum\LogDNALevel;

class Redirect extends AbstractAction
{
    public function execute()
    {
        try {
            $order = $this->getOrder();
            $payment = $order->getPayment();

            if ($payment->getAdditionalInformation('xendit_redirect_url') !== null) {
                $redirectUrl = $payment->getAdditionalInformation('xendit_redirect_url');

                $resultRedirect = $this->getRedirectFactory()->create();
                $resultRedirect->setUrl($redirectUrl);
                return $resultRedirect;
            }

            if ($payment->getAdditionalInformation('xendit_charge_id') !== null) {
                $chargeId = $payment->getAdditionalInformation('xendit_charge_id');
                $orderState = Order::STATE_PROCESSING;
                $order->setState($orderState)
                    ->setStatus($orderState)
                    ->addStatusHistoryComment("Xendit payment completed without 3DS. Transaction ID: $chargeId");

                $order->save();

                $payment->setTransactionId($chargeId);
                $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE, null, true);

                $this->invoiceOrder($order, $chargeId);

                $this->getMessageManager()->addSuccessMessage(__("Your payment with Xendit is completed"));
                return $this->_redirect('checkout/onepage/success', [ '_secure'=> false ]);
            }

            if ($payment->getAdditionalInformation('xendit_ewallet_id') !== null) {
                $paymentId = $payment->getAdditionalInformation('xendit_ewallet_id');

                return $this->processSuccessfulTransaction(
                    $order,
                    $payment,
                    'Xendit eWallet payment completed. Transaction ID: ',
                    $paymentId
                );
            }

            if ($payment->getAdditionalInformation('xendit_failure_reason') !== null) {
                $failureReason = $payment->getAdditionalInformation('xendit_failure_reason');

                $this->cancelOrder($order, $failureReason);

                $failureReasonInsight = $this->getDataHelper()->failureReasonInsight($failureReason);
                $this->getMessageManager()->addErrorMessage(__(
                    $failureReasonInsight
                ));
                return $this->_redirect('checkout/cart', [ '_secure'=> false ]);
            }

            if ($payment->getAdditionalInformation('xendit_hosted_payment_id') !== null) {
                $hostedPaymentId = $payment->getAdditionalInformation('xendit_hosted_payment_id');
                $hostedPaymentToken = $payment->getAdditionalInformation('xendit_hosted_payment_token');
                $data = [
                    'id' => $hostedPaymentId,
                    'hp_token' => $hostedPaymentToken,
                    'order_id' => $order->getRealOrderId()
                ];

                $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
                $result->setData($data);
                return $result;
            }

            $message = 'No action on xendit/checkout/redirect';
            $this->getLogDNA()->log(LogDNALevel::ERROR, $message);

            $this->cancelOrder($order, 'No payment recorded');

            $this->getMessageManager()->addErrorMessage(__(
                "There was an error in the Xendit payment. Failure reason: No payment recorded"
            ));
            return $this->_redirect('checkout/cart', [ '_secure'=> false ]);
        } catch (\Exception $e) {
            $message = 'Exception caught on xendit/checkout/redirect: ' . $e->getMessage();
            $this->getLogDNA()->log(LogDNALevel::ERROR, $message);

            $this->cancelOrder($order, $e->getMessage());

            $this->getMessageManager()->addErrorMessage(__(
                "There was an error in the Xendit payment. Failure reason: Unexpected Error"
            ));
            return $this->_redirect('checkout/cart', [ '_secure'=> false ]);
        }
    }

    private function processSuccessfulTransaction($order, $payment, $paymentMessage, $transactionId)
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
        $this->_redirect('checkout/onepage/success', [ '_secure'=> false ]);
        return;
    }
}
