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
                    $tokenId        = $additionalInfo['token_id'];
                    $cvn            = $additionalInfo['cc_cid'];
                }
    
                $transactionAmount  += (int)$order->getTotalDue();
                $currency = $order->getBaseCurrencyCode();
                $c++;
            }

            if ($orderProcessed) {
                return $this->_redirect('multishipping/checkout/success');
            }

            if ($method === 'cc_subscription') {
                $billingAddress     = $orders[0]->getBillingAddress(); // billing address of 1st order

                $requestData = [
                    'payer_email'               => $billingAddress->getEmail(),
                    'currency'                  => $currency,
                    'order_number'              => $rawOrderIds,
                    'amount'                    => $transactionAmount,
                    'payment_type'              => 'CREDIT_CARD_SUBSCRIPTION',
                    'store_name'                => $this->getStoreManager()->getStore()->getName(),
                    'platform_name'             => 'MAGENTO2',
                    'success_redirect_url'      => $this->getDataHelper()->getSuccessUrl(true),
                    'failure_redirect_url'      => $this->getDataHelper()->getFailureUrl($orderIncrementIds, true),
                    'platform_callback_url'     => $this->getDataHelper()->getCCCallbackUrl(true),// '?order_ids=' . $rawOrderIds,
                    'is_subscription'           => 'true',
                    'subscription_callback_url' => $this->getDataHelper()->getXenditSubscriptionCallbackUrl(true),
                    'subscription_option'       => json_encode(
                        [
                            'interval' => $this->getDataHelper()->getCcSubscriptionInterval(),
                            'interval_count' => $this->getDataHelper()->getCcSubscriptionIntervalCount()
                        ], JSON_FORCE_OBJECT
                    )
                ];
                
                $hostedPayment = $this->requestHostedPayment($requestData);

                if (isset($hostedPayment['error_code'])) {
                    $message = isset($hostedPayment['message']) ? $hostedPayment['message'] : $hostedPayment['error_code'];
                    // cancel order and redirect to cart
                    return $this->processFailedPayment($orderIds, $message);
                } else if (isset($hostedPayment['id'])) {
                    $this->addCCHostedData($orders, $hostedPayment);

                    // redirect to hosted payment page
                    $redirect = $this->getDataHelper()->getUiUrl()."/hosted-payments/".$hostedPayment['id']."?hp_token=".$hostedPayment['hp_token'];
                    $resultRedirect = $this->getRedirectFactory()->create();
                    $resultRedirect->setUrl($redirect);

                    return $resultRedirect;
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
        $hostedPaymentUrl = $this->getDataHelper()->getCheckoutUrl() . "/payment/xendit/hosted-payments";
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
}
