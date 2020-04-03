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
            $orderId = $order->getRealOrderId();
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

            if ($payment->getAdditionalInformation('xendit_ovo_external_id') !== null) {
                $isSuccessful = false;
                $loopCondition = true;
                $startTime = time();
                while ($loopCondition && (time() - $startTime < 60)) {
                    $order = $this->getOrderById($orderId);

                    if ($order->getState() !== Order::STATE_PAYMENT_REVIEW) {
                        $loopCondition = false;
                        $isSuccessful = $order->getState() === Order::STATE_PROCESSING;
                    }
                    sleep(1);
                }

                if ($order->getState() === Order::STATE_PAYMENT_REVIEW) {
                    $ewalletStatus = $this->getEwalletStatus('OVO', $payment->getAdditionalInformation('xendit_ovo_external_id'));

                    if ($ewalletStatus === 'COMPLETED') {
                        $isSuccessful = true;
                    }
                }

                if ($isSuccessful) {
                    $this->getMessageManager()->addSuccessMessage(__("Your payment with Xendit is completed"));
                    return $this->_redirect('checkout/onepage/success', [ '_secure'=> false ]);
                } else {
                    $failureCode = $payment->getAdditionalInformation('xendit_ewallet_failure_code');

                    if ($failureCode === null) {
                        $failureCode = 'Order status is ' . $ewalletStatus;
                    }

                    $payment->setAdditionalInformation('xendit_failure_reason', $failureCode);
                }
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

    private function getEwalletStatus($ewalletType, $externalId)
    {
        $ewalletUrl = $this->getDataHelper()->getCheckoutUrl() . "/payment/xendit/ewallets?ewallet_type=".$ewalletType."&external_id=".$externalId;
        $ewalletMethod = \Zend\Http\Request::METHOD_GET;

        try {
            $response = $this->getApiHelper()->request($ewalletUrl, $ewalletMethod);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            throw new LocalizedException(
                new Phrase($e->getMessage())
            );
        }

        if ($ewalletType == 'DANA') {
            $response['status'] = $response['payment_status'];
        }

        $statusList = array("COMPLETED", "PAID", "SUCCESS_COMPLETED"); //OVO, DANA, LINKAJA
        if (in_array($response['status'], $statusList)) {
            return "COMPLETED";
        }
        
        return $response['status'];
    }
}
