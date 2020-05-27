<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Model\Order;
use Xendit\M2Invoice\Enum\LogDNALevel;

class CCMultishipping extends AbstractAction
{
    public function execute()
    {
        try {
            $rawOrderIds        = $this->getRequest()->getParam('order_ids');
            $orderIds           = explode("|", $rawOrderIds);

            $transactionAmount  = 0;
            $incrementIds       = [];
            $tokenId            = '';

            foreach ($orderIds as $key => $value) {
                $order              = $this->getOrderFactory()->create();
                $order->load($value);

                $order->setState(Order::STATE_PENDING_PAYMENT)
                    ->setStatus(Order::STATE_PENDING_PAYMENT)
                    ->addStatusHistoryComment("Pending Xendit payment.");
                $order->save();

                $payment            = $order->getPayment();
                $quoteId            = $order->getQuoteId();
                $quote              = $this->getQuoteRepository()->get($quoteId);

                $additionalInfo     = $quote->getPayment()->getAdditionalInformation();
                $tokenId            = $additionalInfo['token_id'];
                $cvn                = $additionalInfo['cc_cid'];
    
                $transactionAmount  += (int)$order->getTotalDue();
                $incrementIds[]     = $order->getIncrementId();
            }

            $externalIdSuffix = implode("|", $incrementIds);
            $requestData = array(
                'token_id' => $tokenId,
                'card_cvn' => $cvn,
                'amount' => $transactionAmount,
                'external_id' => $this->getDataHelper()->getExternalId($externalIdSuffix),
                'return_url' => $this->getDataHelper()->getThreeDSResultUrl($externalIdSuffix)
            );

            $charge = $this->requestCharge($requestData);

            $chargeError = isset($charge['error_code']) ? $charge['error_code'] : null;
            if ($chargeError == 'EXTERNAL_ID_ALREADY_USED_ERROR') {
                $newRequestData = array_replace($requestData, array(
                    'external_id' => $this->getDataHelper()->getExternalId($externalIdSuffix, true)
                ));
                $charge = $this->requestCharge($newRequestData);
            }

            $chargeError = isset($charge['error_code']) ? $charge['error_code'] : null;
            if ($chargeError == 'AUTHENTICATION_ID_MISSING_ERROR') {
                return $this->handle3DSFlow($requestData, $payment, $incrementIds);
            }

            if ($chargeError !== null) {
                return $this->processFailedPayment($incrementIds, $payment, $chargeError);
            }

            if ($charge['status'] === 'CAPTURED') {
                return $this->processSuccessfulPayment($incrementIds, $payment, $charge);
            } else {
                return $this->processFailedPayment($incrementIds, $payment, $charge['failure_reason']);
            }
        } catch (\Exception $e) {
            $message = 'Exception caught on xendit/checkout/redirect: ' . $e->getMessage();
            return $this->redirectToCart("There was an error in the Xendit payment. Failure reason: Unexpected Error");
        }
    }

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

    private function handle3DSFlow($requestData, $payment, $orderIds)
    {
        unset($requestData['card_cvn']);
        $hosted3DSRequestData = array_replace([], $requestData);
        $hosted3DS = $this->request3DS($hosted3DSRequestData);

        $hosted3DSError = isset($hosted3DS['error_code']) ? $hosted3DS['error_code'] : null;

        if ($hosted3DSError !== null) {
            return $this->processFailedPayment($orderIds, $payment, $hosted3DSError);
        }

        if ('IN_REVIEW' === $hosted3DS['status']) {
            $hostedUrl = $hosted3DS['redirect']['url'];
            $hostedId = $hosted3DS['id'];
            $payment->setAdditionalInformation('payment_gateway', 'xendit');
            $payment->setAdditionalInformation('xendit_redirect_url', $hostedUrl);
            $payment->setAdditionalInformation('xendit_hosted_3ds_id', $hostedId);

            foreach ($orderIds as $key => $value) {
                $order = $this->getOrderById($value);

                $order->addStatusHistoryComment("Xendit payment waiting for authentication. 3DS id: $hostedId");
                $order->save();
            }

            $resultRedirect = $this->getRedirectFactory()->create();
            $resultRedirect->setUrl($hostedUrl);
            return $resultRedirect;
        }

        if ('VERIFIED' === $hosted3DS['status']) {
            $newRequestData = array_replace($requestData, array(
                'authentication_id' => $hosted3DS['authentication_id']
            ));
            $charge = $this->requestCharge($newRequestData);

            if ($charge['status'] === 'CAPTURED') {
                return $this->processSuccessfulPayment($orderIds, $payment, $charge);
            } else {
                return $this->processFailedPayment($orderIds, $payment, $charge['failure_reason']);
            }
        }

        return $this->processFailedPayment($orderIds, $payment);
    }

    private function processFailedPayment($orderIds, $payment, $failureReason = 'Unexpected Error with empty charge')
    {
        foreach ($orderIds as $key => $value) {
            $order = $this->getOrderById($value);

            $orderState = Order::STATE_CANCELED;
            $order->setState($orderState)
                ->setStatus($orderState)
                ->addStatusHistoryComment("Order #" . $order->getId() . " was rejected by Xendit because " .
                    $failureReason);
            $order->save();
        }

        $failureReasonInsight = $this->getDataHelper()->failureReasonInsight($failureReason);
        $this->getMessageManager()->addErrorMessage(__(
            $failureReasonInsight
        ));
        $this->_redirect('checkout/cart', array('_secure'=> false));
    }

    private function processSuccessfulPayment($orderIds, $payment, $charge)
    {
        $transactionId = $charge['id'];
        $payment->setTransactionId($transactionId);
        $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE, null, true);

        foreach ($orderIds as $key => $value) {
            $order = $this->getOrderById($value);
            $orderState = Order::STATE_PROCESSING;

            $order->setState($orderState)
                ->setStatus($orderState)
                ->addStatusHistoryComment("Xendit payment completed. Transaction ID: $transactionId");
            $order->save();

            $this->invoiceOrder($order, $transactionId);
        }

        $this->getMessageManager()->addSuccessMessage(__("Your payment with Xendit is completed"));
        $this->_redirect('checkout/onepage/success', [ '_secure'=> false ]);
    }

    private function request3DS($requestData)
    {
        $hosted3DSUrl = $this->getDataHelper()->getCheckoutUrl() . "/payment/xendit/credit-card/hosted-3ds";
        $hosted3DSMethod = \Zend\Http\Request::METHOD_POST;

        try {
            $hosted3DS = $this->getApiHelper()->request($hosted3DSUrl, $hosted3DSMethod, $requestData, true);
        } catch (\Exception $e) {
            throw $e;
        }

        return $hosted3DS;
    }

    private function requestCharge($requestData)
    {
        $chargeUrl = $this->getDataHelper()->getCheckoutUrl() . "/payment/xendit/credit-card/charges";
        $chargeMethod = \Zend\Http\Request::METHOD_POST;

        try {
            $hosted3DS = $this->getApiHelper()->request($chargeUrl, $chargeMethod, $requestData);
        } catch (\Exception $e) {
            throw $e;
        }

        return $hosted3DS;
    }
}
