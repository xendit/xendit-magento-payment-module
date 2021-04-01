<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;
use Zend\Http\Request;

/**
 * Class CCMultishipping
 * @package Xendit\M2Invoice\Controller\Checkout
 */
class CCMultishipping extends AbstractAction
{
    public function execute()
    {
        $customerSession = $this->getCustomerSession();

        try {
            $rawOrderIds        = $this->getRequest()->getParam('order_ids');
            $method             = $this->getRequest()->getParam('preferred_method');
            $orderIds           = explode("-", $rawOrderIds);

            $transactionAmount  = 0;
            $tokenId            = '';
            $orderProcessed     = false;
            $orders             = [];

            if ($method === 'cc_subscription' && !$customerSession->isLoggedIn()) {
                $message = 'You must logged in to use this payment method';
                $this->getLogger()->info($message);
                return $this->redirectToCart($message);
            }

            $orderIncrementIds = '';

            $c = 0;

            foreach ($orderIds as $key => $value) {
                $order = $this->getOrderFactory()->create();
                $order  ->load($value);
                if ($c>0) {
                    $orderIncrementIds .= "-";
                }
                $orderIncrementIds .= $order->getRealOrderId();

                $orderState = $order->getState();
                if ($orderState === Order::STATE_PROCESSING && !$order->canInvoice()) {
                    $orderProcessed = true;
                    continue;
                }

                $order  ->setState(Order::STATE_PENDING_PAYMENT)
                        ->setStatus(Order::STATE_PENDING_PAYMENT)
                        ->addStatusHistoryComment("Pending Xendit payment.");
                // save order
                $order  ->save();

                $orders[]           = $order;
                $payment            = $order->getPayment();
                $quoteId            = $order->getQuoteId();
                $quote              = $this->getQuoteRepository()->get($quoteId);

                $additionalInfo     = $quote->getPayment()->getAdditionalInformation();
                if (isset($additionalInfo['token_id']) && isset($additionalInfo['cc_cid'])) {
                    $tokenId            = $additionalInfo['token_id'];
                    $cvn                = $additionalInfo['cc_cid'];
                }
    
                $transactionAmount  += (int)$order->getTotalDue();
                $c++;
            }

            if ($orderProcessed) {
                return $this->_redirect('multishipping/checkout/success');
            }

            if ($method === 'cc_subscription') {
                $billingAddress     = $orders[0]->getBillingAddress(); // billing address of 1st order
                $shippingAddress    = $orders[0]->getShippingAddress(); // shipping address of 1st order

                $requestData = [
                    'external_id'            => $this->getDataHelper()->getExternalId($rawOrderIds),
                    'payer_email'            => $billingAddress->getEmail(),
                    'description'            => $rawOrderIds,
                    'order_number'           => $rawOrderIds,
                    'amount'                 => $transactionAmount,
                    'payment_type'           => 'CREDIT_CARD',
                    'store_name'             => $this->getStoreManager()->getStore()->getName(),
                    'platform_name'          => 'MAGENTO2',
                    'success_redirect_url'   => $this->getDataHelper()->getSuccessUrl(true),
                    'failure_redirect_url'   => $this->getDataHelper()->getFailureUrl($orderIncrementIds, true),
                    'platform_callback_url'  => $this->_url->getUrl('xendit/checkout/cccallback') . '?order_ids=' . $rawOrderIds
                ];

                $requestData['payment_type'] = 'CREDIT_CARD_SUBSCRIPTION';
                $requestData['is_subscription'] = "true";
                $requestData['subscription_callback_url'] = $this->getDataHelper()->getXenditSubscriptionCallbackUrl(true);
                $requestData['payer_email'] = $billingAddress->getEmail();
                $requestData['subscription_option'] = json_encode(
                    [
                        'interval' => $this->getDataHelper()->getCcSubscriptionInterval(),
                        'interval_count' => $this->getDataHelper()->getCcSubscriptionIntervalCount()
                    ], JSON_FORCE_OBJECT
                );
                
                $hostedPayment = $this->requestHostedPayment($requestData);

                if (isset($hostedPayment['error_code'])) {
                    $message = $this->getErrorHandler()->mapInvoiceErrorCode($hostedPayment['error_code']);
                    // cancel order and redirect to cart
                    return $this->processFailedPayment($orderIds, $message);
                } elseif (isset($hostedPayment['id'])) {

                    $this->addCCHostedData($orders, $hostedPayment);

                    // redirect to hosted payment page
                    if (isset($hostedPayment['invoice_url'])) {
                        $redirect = $hostedPayment['invoice_url']."#credit-card";
                        $resultRedirect = $this->getRedirectFactory()->create();
                        $resultRedirect->setUrl($redirect);

                        return $resultRedirect;
                    }
                } else {
                    $message = 'Error connecting to Xendit. Please check your API key.';
                    return $this->processFailedPayment($orderIds, $message);
                }
            }
        } catch (\Exception $e) {
            $message = 'Exception caught on xendit/checkout/redirect: ' . $e->getMessage();
            $this->getLogger()->info($message);
            return $this->redirectToCart("There was an error in the Xendit payment. Failure reason: Unexpected Error");
        }
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
        $resultRedirect->setUrl($this->_url->getUrl('checkout/cart'));
        return $resultRedirect;
    }

    /**
     * @param $requestData
     * @return mixed
     * @throws \Exception
     */
    private function requestHostedPayment($requestData)
    {
        $hostedPaymentUrl = $this->getDataHelper()->getCheckoutUrl() . "/v2/invoices#credit-card";
        $hostedPaymentMethod = Request::METHOD_POST;

        try {
            $hostedPayment = $this->getApiHelper()->request(
                $hostedPaymentUrl,
                $hostedPaymentMethod,
                $requestData
            );
        } catch (\Exception $e) {
            throw $e;
        }

        return $hostedPayment;
    }

    /**
     * @param $orders
     * @param $data
     */
    private function addCCHostedData($orders, $data)
    {
        foreach ($orders as $key => $order) {
            $payment = $order->getPayment();
            $payment->setAdditionalInformation('payment_gateway', 'xendit');
            if (isset($data['id'])) {
                $payment->setAdditionalInformation('xendit_hosted_payment_id', $data['id']);
            }
            if (isset($data['hp_token'])) {
                $payment->setAdditionalInformation('xendit_hosted_payment_token', $data['hp_token']);
            }

            $order->save();
        }
    }

    /**
     * @param $requestData
     * @param $payment
     * @param $orderIds
     * @param $orders
     * @return \Magento\Framework\Controller\Result\Redirect|void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function handle3DSFlow($requestData, $payment, $orderIds, $orders)
    {
        unset($requestData['card_cvn']);
        $hosted3DSRequestData = array_replace([], $requestData);
        $hosted3DS = $this->request3DS($hosted3DSRequestData);

        $hosted3DSError = isset($hosted3DS['error_code']) ? $hosted3DS['error_code'] : null;

        if ($hosted3DSError !== null) {
            return $this->processFailedPayment($orderIds, $hosted3DSError);
        }

        if ('IN_REVIEW' === $hosted3DS['status']) {
            $hostedUrl = $hosted3DS['redirect']['url'];
            $hostedId = $hosted3DS['id'];
            $payment->setAdditionalInformation('payment_gateway', 'xendit');
            $payment->setAdditionalInformation('xendit_redirect_url', $hostedUrl);
            $payment->setAdditionalInformation('xendit_hosted_3ds_id', $hostedId);

            foreach ($orderIds as $key => $value) {
                $order = $this->getOrderFactory()->create();
                $order->load($value);
                $order->addStatusHistoryComment("Xendit payment waiting for authentication. 3DS ID: $hostedId");
                $order->save();
            }

            $resultRedirect = $this->getRedirectFactory()->create();
            $resultRedirect->setUrl($hostedUrl);
            return $resultRedirect;
        }

        if ('VERIFIED' === $hosted3DS['status']) {
            $newRequestData = array_replace($requestData, [
                'authentication_id' => $hosted3DS['authentication_id']
            ]);
            $charge = $this->requestCharge($newRequestData);

            if ($charge['status'] === 'CAPTURED') {
                return $this->processSuccessfulPayment($orders, $payment, $charge);
            } else {
                return $this->processFailedPayment($orderIds, $charge['failure_reason']);
            }
        }

        return $this->processFailedPayment($orderIds);
    }

    /**
     * @param $orderIds
     * @param string $failureReason
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function processFailedPayment($orderIds, $failureReason = 'Unexpected Error with empty charge')
    {
        $this->getCheckoutHelper()->processOrdersFailedPayment($orderIds, $failureReason);

        $failureReasonInsight = $this->getDataHelper()->failureReasonInsight($failureReason);
        $this->getMessageManager()->addErrorMessage(__(
            $failureReasonInsight
        ));
        $this->_redirect('checkout/cart', ['_secure'=> false]);
    }

    /**
     * @param $orders
     * @param $payment
     * @param $charge
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function processSuccessfulPayment($orders, $payment, $charge)
    {
        $transactionId = $charge['id'];
        $payment->setTransactionId($transactionId);
        $payment->addTransaction(Transaction::TYPE_CAPTURE, null, true);

        foreach ($orders as $key => $order) {
            $orderState = Order::STATE_PROCESSING;

            $order->setState($orderState)
                  ->setStatus($orderState)
                  ->addStatusHistoryComment("Xendit payment completed. Transaction ID: $transactionId");

            $payment = $order->getPayment();
            $payment->setTransactionId($transactionId);
            $payment->addTransaction(Transaction::TYPE_CAPTURE, null, true);

            $order->save();

            $this->invoiceOrder($order, $transactionId);
        }

        $this->_redirect($this->getDataHelper()->getSuccessUrl(true));
    }

    /**
     * @param $requestData
     * @return mixed
     * @throws \Exception
     */
    private function request3DS($requestData)
    {
        $hosted3DSUrl = $this->getDataHelper()->getCheckoutUrl() . "/payment/xendit/credit-card/hosted-3ds";
        $hosted3DSMethod = Request::METHOD_POST;

        try {
            $hosted3DS = $this->getApiHelper()->request($hosted3DSUrl, $hosted3DSMethod, $requestData, true);
        } catch (\Exception $e) {
            throw $e;
        }

        return $hosted3DS;
    }

    /**
     * @param $requestData
     * @return mixed
     * @throws \Exception
     */
    private function requestCharge($requestData)
    {
        $chargeUrl = $this->getDataHelper()->getCheckoutUrl() . "/payment/xendit/credit-card/charges";
        $chargeMethod = Request::METHOD_POST;

        try {
            $hosted3DS = $this->getApiHelper()->request($chargeUrl, $chargeMethod, $requestData);
        } catch (\Exception $e) {
            throw $e;
        }

        return $hosted3DS;
    }
}
