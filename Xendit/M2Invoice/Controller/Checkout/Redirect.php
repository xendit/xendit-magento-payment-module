<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Model\Order;
use Xendit\M2Invoice\Enum\LogDNALevel;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Framework\Exception\LocalizedException;

/**
 * Class Redirect
 * @package Xendit\M2Invoice\Controller\Checkout
 */
class Redirect extends AbstractAction
{
    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     * @throws \Magento\Framework\Exception\LocalizedException
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
            // Dana / Kredivo / Linkaja / CC - Hosted
            if ($payment->getAdditionalInformation('xendit_redirect_url') !== null) {
                $redirectUrl = $payment->getAdditionalInformation('xendit_redirect_url');

                $resultRedirect = $this->getRedirectFactory()->create();
                $resultRedirect->setUrl($redirectUrl);
                return $resultRedirect;
            }
            // CC - Form (not use anymore)
            if ($payment->getAdditionalInformation('xendit_charge_id') !== null) {
                $chargeId = $payment->getAdditionalInformation('xendit_charge_id');
                $orderState = Order::STATE_PROCESSING;
                $order->setState($orderState)
                    ->setStatus($orderState)
                    ->addStatusHistoryComment("Xendit payment completed without 3DS. Transaction ID: $chargeId");

                $order->save();

                $payment->setTransactionId($chargeId);
                $payment->addTransaction(Transaction::TYPE_CAPTURE, null, true);

                $this->invoiceOrder($order, $chargeId);

                $this->getMessageManager()->addSuccessMessage(__("Your payment with Xendit is completed"));
                return $this->_redirect('checkout/onepage/success', [ '_secure'=> false ]);
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
            // OVO payment
            if ($payment->getAdditionalInformation('xendit_ovo_external_id') !== null) {
                $isSuccessful = false;
                $loopCondition = true;
                $startTime = time();
                while ($loopCondition && (time() - $startTime < 70)) {
                    $order = $this->getOrderById($orderId);

                    if ($order->getState() !== Order::STATE_PENDING_PAYMENT) {
                        $loopCondition = false;
                        $isSuccessful = $order->getState() === Order::STATE_PROCESSING;
                    }
                    sleep(1);
                }

                if ($order->getState() === Order::STATE_PENDING_PAYMENT) {
                    $ewalletStatus = $this->getEwalletStatus('OVO', $payment->getAdditionalInformation('xendit_ovo_external_id'));

                    if ($ewalletStatus === 'COMPLETED') {
                        $isSuccessful = true;
                    }
                }

                if ($isSuccessful) {
                    $this->getMessageManager()->addSuccessMessage(__("Your payment with Xendit is completed"));
                    return $this->_redirect('checkout/onepage/success', [ '_secure'=> false ]);
                } else {
                    $payment = $order->getPayment();
                    $failureCode = $payment->getAdditionalInformation('xendit_ewallet_failure_code');

                    if ($failureCode === null) {
                        $failureCode = 'Payment is ' . $ewalletStatus;
                    }

                    $this->getCheckoutHelper()->restoreQuote();
                    return $this->redirectToCart($failureCode);
                }
            }
            // Credit Card - Hosted
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

            return $this->redirectToCart("There was an error in the Xendit payment. Failure reason: Unexpected Error");

        } catch (\Exception $e) {
            $message = 'Exception caught on xendit/checkout/redirect: ' . $e->getMessage();
            $this->getLogDNA()->log(LogDNALevel::ERROR, $message);

            $this->cancelOrder($order, $e->getMessage());

            return $this->redirectToCart("There was an error in the Xendit payment. Failure reason: Unexpected Error");
        }
    }

    /**
     * @param $ewalletType
     * @param $externalId
     * @return string
     * @throws LocalizedException
     */
    private function getEwalletStatus($ewalletType, $externalId)
    {
        $ewalletUrl = $this->getDataHelper()->getCheckoutUrl() . "/ewallets?ewallet_type=".$ewalletType."&external_id=".$externalId;
        $ewalletMethod = \Zend\Http\Request::METHOD_GET;

        try {
            $response = $this->getApiHelper()->request($ewalletUrl, $ewalletMethod);
        } catch (LocalizedException $e) {
            throw new LocalizedException(
                new Phrase($e->getMessage())
            );
        }

        $statusList = array("COMPLETED", "PAID", "SUCCESS_COMPLETED"); //OVO, DANA, LINKAJA
        if (in_array($response['status'], $statusList)) {
            return "COMPLETED";
        }
        
        return $response['status'];
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
