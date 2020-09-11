<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Xendit\M2Invoice\Enum\LogDNALevel;

class CCCallback extends ProcessHosted implements CsrfAwareActionInterface
{
    public function execute()
    {
        try {
            $orderIds = explode('-', $this->getRequest()->getParam('order_ids'));
            
            $shouldRedirect = false;
            $isError = false;
            $flag = true;

            foreach ($orderIds as $key => $value) {
                $order = $this->getOrderFactory()->create();
                $order  ->load($value);

                $payment = $order->getPayment();

                if ($payment->getAdditionalInformation('xendit_hosted_payment_id') !== null) {
                    $requestData = [
                        'id' => $payment->getAdditionalInformation('xendit_hosted_payment_id'),
                        'hp_token' => $payment->getAdditionalInformation('xendit_hosted_payment_token')
                    ];
    
                    if ($flag) { // complete hosted payment only once as status will be changed to USED
                        $hostedPayment = $this->getCompletedHostedPayment($requestData);
                        $flag = false;
                    }
                    
                    if (isset($hostedPayment['error_code'])) {
                        $isError = true;
                    }
                    else {
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
                            $hostedPayment['charge_id']
                        );
                    }
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
            throw new LocalizedException(
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
                throw new LocalizedException(
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

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
