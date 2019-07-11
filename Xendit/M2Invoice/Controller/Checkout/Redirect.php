<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Sales\Model\Order;
use Xendit\M2Invoice\Enum\LogDNALevel;

class Redirect extends AbstractAction
{
    public function execute()
    {
        try {
            $order = $this->getOrder();
            $payment = $order->getPayment();
            $logresp = $this->getLogDNA()->log(LogDNALevel::INFO, "Testing logDNA magento");
            $this->getLogger()->debug("Testing logDNA magento" . print_r($logresp));

            return;

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
                $this->_redirect('checkout/onepage/success', [ '_secure'=> false ]);
                return;
            }

            if ($payment->getAdditionalInformation('xendit_failure_reason') !== null) {
                $failureReason = $payment->getAdditionalInformation('xendit_failure_reason');

                $orderState = Order::STATE_CANCELED;
                $order->setState($orderState)
                    ->setStatus($orderState)
                    ->addStatusHistoryComment("Order #" . $order->getId() . " was rejected by Xendit because " .
                        $failureReason);
                $order->save();

                $this->getCheckoutHelper()->cancelOrderById($order->getId(),
                    "Order #".($order->getId())." was rejected by Xendit");
                $this->getCheckoutHelper()->restoreQuote(); //restore cart

                $this->getMessageManager()->addErrorMessage(__(
                    "There was an error in the Xendit payment. Failure reason: $failureReason"
                ));
                $this->_redirect('checkout/cart', [ '_secure'=> false ]);
                return;
            }
        } catch (\Exception $e) {
            $message = 'Exception caught on xendit/checkout/redirect: ' . $e->getMessage();
            $this->getLogDNA()->log(LogDNALevel::ERROR, $message);
            $this->getLogger()->debug($message);
            $this->getLogger()->debug($e->getTraceAsString());
        }
    }
}
