<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Model\Order;
use Xendit\M2Invoice\Enum\LogDNALevel;

/**
 * This callback is only for order in multishipping flow. For order
 * created in onepage checkout is handled in ProcessHosted.php
 */
class CCCallback extends ProcessHosted
{
    public function execute()
    {
        try {
            $post = $this->getRequest()->getContent();
            $callbackPayload = json_decode($post, true);

            if (
                !isset($callbackPayload['id']) ||
                !isset($callbackPayload['hp_token']) ||
                !isset($callbackPayload['order_number'])
            ) {
                $result = $this->getJsonResultFactory()->create();
                $result->setHttpResponseCode(\Magento\Framework\Webapi\Exception::HTTP_BAD_REQUEST);
                $result->setData([
                    'status' => __('ERROR'),
                    'message' => 'Callback body is invalid'
                ]);

                return $result;
            }
            $orderIds = explode('-', $callbackPayload['order_number']);
            $hostedPaymentId = $callbackPayload['id'];
            $hostedPaymentToken = $callbackPayload['hp_token'];
            
            $shouldRedirect = false;
            $isError = false;
            $flag = true;

            foreach ($orderIds as $key => $value) {
                $order = $this->getOrderFactory()->create();
                $order  ->load($value);

                $payment = $order->getPayment();

                $requestData = [
                    'id' => $hostedPaymentId,
                    'hp_token' => $hostedPaymentToken
                ];

                if ($flag) { // complete hosted payment only once as status will be changed to USED
                    $hostedPayment = $this->getCompletedHostedPayment($requestData);
                    $flag = false;
                }
                
                if (isset($hostedPayment['error_code'])) {
                    $isError = true;
                }
                else {
                    if ($hostedPayment['order_number'] !== $callbackPayload['order_number']) {
                        $result = $this->getJsonResultFactory()->create();
                        $result->setHttpResponseCode(\Magento\Framework\Webapi\Exception::HTTP_BAD_REQUEST);
                        $result->setData([
                            'status' => __('ERROR'),
                            'message' => 'Hosted payment is not for this order'
                        ]);

                        return $result;
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
    
                    $this->processSuccessfulTransaction(
                        $order,
                        $payment,
                        'Xendit Credit Card payment completed. Transaction ID: ',
                        $hostedPayment['charge_id']
                    );
                }
            }

            $result = $this->getJsonResultFactory()->create();

            if ($isError) {
                $result->setHttpResponseCode(\Magento\Framework\Webapi\Exception::HTTP_BAD_REQUEST);
                $result->setData([
                    'status' => __('ERROR'),
                    'message' => 'Callback error: ' . $hostedPayment['error_code']
                ]);
            } else {
                $result->setData([
                    'status' => __('OK'),
                    'message' => 'Callback processed successfully.'
                ]);
            }
            return $result;
            
        } catch (\Exception $e) {
            $result = $this->getJsonResultFactory()->create();
            $result->setHttpResponseCode(\Magento\Framework\Webapi\Exception::HTTP_BAD_REQUEST);
            $result->setData([
                'status' => __('ERROR'),
                'message' => $e->getMessage()
            ]);

            return $result;
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
                __($e->getMessage())
            );
        }

        return $hostedPayment;
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
    }

    protected function invoiceOrder($order, $transactionId)
    {
        if ($order->canInvoice()) {
            $invoice = $this->getObjectManager()
                ->create('Magento\Sales\Model\Service\InvoiceService')
                ->prepareInvoice($order);
            
            if (!$invoice->getTotalQty()) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('You can\'t create an invoice without products.')
                );
            }
            
            $invoice->setTransactionId($transactionId);
            $invoice->setRequestedCaptureCase(Order\Invoice::CAPTURE_OFFLINE);
            $invoice->register();
            $transaction = $this->getObjectManager()->create('Magento\Framework\DB\Transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder());
            $transaction->save();
        }
    }
}
