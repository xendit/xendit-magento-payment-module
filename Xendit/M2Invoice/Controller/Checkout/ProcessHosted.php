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
            $orders = [];
            $process = $this->getRequest()->getParam('process');

            if ($process === 'callback') { // multishipping
                $post = $this->getRequest()->getContent();
                $callbackToken = $this->getRequest()->getHeader('X-CALLBACK-TOKEN');
                $decodedPost = json_decode($post, true);

                $orderIds = explode('-', $this->getRequest()->getParam('order_ids'));
                foreach ($orderIds as $key => $value) {
                    $o = $this->getOrderFactory()->create();
                    $o ->load($value);
                    $orders[] = $o;
                }
                
                $shouldRedirect = 0;
            }
            else {
                $orders[] = $this->getOrder();
                $shouldRedirect = 1;
            }
            
            $isError = 0;

            foreach ($orders AS $order) {
                $payment = $order->getPayment();

                if ($payment->getAdditionalInformation('xendit_hosted_payment_id') !== null) {
                    $requestData = [
                        'id' => $payment->getAdditionalInformation('xendit_hosted_payment_id'),
                        'hp_token' => $payment->getAdditionalInformation('xendit_hosted_payment_token')
                    ];
    
                    $hostedPayment = $this->getCompletedHostedPayment($requestData);
    
                    if (isset($hostedPayment['error_code'])) {
                        $isError = 1;
                        $this->handlePaymentFailure($order, $hostedPayment['error_code'], 'Error reconciliating', $shouldRedirect);
                    }
    
                    if ($hostedPayment['paid_amount'] != $hostedPayment['amount']) {
                        $order->setBaseDiscountAmount($hostedPayment['paid_amount'] - $hostedPayment['amount']);
                        $order->setDiscountAmount($hostedPayment['paid_amount'] - $hostedPayment['amount']);
                        $order->save();
        
                        $order->setBaseGrandTotal($order->getBaseGrandTotal() + $order->getBaseDiscountAmount());
                        $order->setGrandTotal($order->getGrandTotal() + $order->getDiscountAmount());
                        $order->save();
                    }
    
                    $this->processSuccessfulTransaction(
                        $order,
                        $payment,
                        'Xendit Credit Card payment completed. Transaction ID: ',
                        $hostedPayment['charge_id'],
                        $shouldRedirect
                    );
                }
            }

            if ($process === 'callback') {
                if ($isError) {
                    // only redirect to cart page once all orders have been processed as failed.
                    return $this->_redirect('checkout/cart', [ '_secure'=> false ]);
                }
                else { // no error means redirect to multishipping success page from `processSuccessfulTransaction`
                    return $this->_redirect('*/*/success', [ '_secure'=> false ]);
                }
            }
            else {
                $message = 'No action on xendit/checkout/redirect';
                return $this->handlePaymentFailure($orders, $message, 'No payment recorded');
            }
        } catch (\Exception $e) {
            $message = 'Exception caught on xendit/checkout/redirect: ' . $e->getMessage();
            return $this->handlePaymentFailure($order, $message, 'Unexpected error');
        }
    }

    private function processSuccessfulTransaction($order, $payment, $paymentMessage, $transactionId, $shouldRedirect = 1)
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

    private function handlePaymentFailure($order, $message, $reason, $shouldRedirect = 1)
    {
        $this->getLogDNA()->log(LogDNALevel::ERROR, $message);

        $this->cancelOrder($order, $reason);

        $this->getMessageManager()->addErrorMessage(__(
            "There was an error in the Xendit payment. Failure reason: $reason"
        ));

        if ($shouldRedirect) {
            return $this->_redirect('checkout/cart', [ '_secure'=> false ]);
        }
    }
}
