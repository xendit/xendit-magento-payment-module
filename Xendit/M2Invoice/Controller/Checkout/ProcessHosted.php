<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Sales\Model\Order;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Payment\Transaction;
use Zend\Http\Request;

/**
 * Class ProcessHosted
 * @package Xendit\M2Invoice\Controller\Checkout
 */
class ProcessHosted extends AbstractAction
{
    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
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
                if (isset($hostedPayment['token_id'])) {
                    $payment->setAdditionalInformation('token_id', $hostedPayment['token_id']);
                }
                if (isset($hostedPayment['installment'])) {
                    $payment->setAdditionalInformation('xendit_installment', $hostedPayment['installment']);
                }

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

    /**
     * @param $order
     * @param $payment
     * @param $paymentMessage
     * @param $transactionId
     * @param bool $shouldRedirect
     * @return \Magento\Framework\App\ResponseInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function processSuccessfulTransaction($order, $payment, $paymentMessage, $transactionId, $shouldRedirect = true)
    {
        $orderState = Order::STATE_PROCESSING;
        $order->setState($orderState)
            ->setStatus($orderState)
            ->addStatusHistoryComment("$paymentMessage $transactionId");

        $order->save();

        $payment->setTransactionId($transactionId);
        $payment->addTransaction(Transaction::TYPE_CAPTURE, null, true);

        $this->invoiceOrder($order, $transactionId);

        $this->getMessageManager()->addSuccessMessage(__("Your payment with Xendit is completed"));

        if ($shouldRedirect) {
            return $this->_redirect('checkout/onepage/success', [ '_secure'=> false ]);
        }
    }

    /**
     * @param $requestData
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getCompletedHostedPayment($requestData)
    {
        $url = $this->getDataHelper()->getCheckoutUrl() . "/payment/xendit/hosted-payments/" . $requestData['id'] . "?hp_token=" . $requestData['hp_token'] . '&statuses[]=COMPLETED';
        $method = Request::METHOD_GET;

        try {
            $hostedPayment = $this->getApiHelper()->request(
                $url, $method
            );
        } catch (LocalizedException $e) {
            throw new LocalizedException(
                new Phrase($e->getMessage())
            );
        }

        return $hostedPayment;
    }

    public function handlePaymentFailure($order, $message, $reason, $shouldRedirect = true)
    {
        $this->cancelOrder($order, $reason);

        $this->getMessageManager()->addErrorMessage(__(
            "There was an error in the Xendit payment. Failure reason: $reason"
        ));

        if ($shouldRedirect) {
            return $this->_redirect('checkout/cart', [ '_secure'=> false ]);
        }
    }
}
