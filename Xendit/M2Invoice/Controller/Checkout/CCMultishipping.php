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
            $method             = $this->getRequest()->getParam('preferred_method');
            $orderIds           = explode("-", $rawOrderIds);

            $transactionAmount  = 0;
            $incrementIds       = [];
            $orderPayments      = [];
            $orderPromos        = [];
            $tokenId            = '';
            $orders             = [];

            foreach ($orderIds as $key => $value) {
                $order = $this->getOrderFactory()->create();
                $order  ->load($value);

                $order  ->setState(Order::STATE_PENDING_PAYMENT)
                        ->setStatus(Order::STATE_PENDING_PAYMENT)
                        ->addStatusHistoryComment("Pending Xendit payment.");
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
                $incrementIds[]     = $order->getIncrementId();

                $orderPayments[]    = $quoteId;
                $orderPromos[]      = $this->calculatePromo($order);
            }

            if ($method === 'cc') {
                $externalIdSuffix = implode("-", $incrementIds);
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
                    return $this->handle3DSFlow($requestData, $payment, $incrementIds, $orders);
                }

                if ($chargeError !== null) {
                    return $this->processFailedPayment($incrementIds, $chargeError);
                }
    
                if ($charge['status'] === 'CAPTURED') {
                    return $this->processSuccessfulPayment($orders, $payment, $charge);
                } else {
                    return $this->processFailedPayment($incrementIds, $charge['failure_reason']);
                }
            }
            else if ($method === 'cchosted') {
                $requestData        = [
                    'order_number'           => $rawOrderIds,
                    'amount'                 => $transactionAmount,
                    'payment_type'           => 'CREDIT_CARD',
                    'store_name'             => $this->getStoreManager()->getStore()->getName(),
                    'platform_name'          => 'MAGENTO2',
                    'success_redirect_url'   => $this->getDataHelper()->getSuccessUrl() . '?type=multishipping',
                    'failure_redirect_url'   => $this->_url->getUrl('checkout/cart'),
                    'platform_callback_url'  => $this->_url->getUrl('xendit/checkout/processhosted') . '?process=callback&order_ids=' . $rawOrderIds
                ];
                // how to append promo?

                $hostedPayment = $this->requestHostedPayment($requestData);

                if (isset($hostedPayment['error_code'])) {
                    $message = isset($hostedPayment['message']) ? $hostedPayment['message'] : $hostedPayment['error_code'];

                    return $this->processFailedPayment($incrementIds, $message);
                } else if (isset($hostedPayment['id'])) {
                    $hostedPaymentId = $hostedPayment['id'];
                    $hostedPaymentToken = $hostedPayment['hp_token'];

                    foreach ($orderPayments as $key => $value) {
                        $quote = $this->getQuoteRepository()->get($value);
                        $payment = $quote->getPayment();

                        $payment->setAdditionalInformation('xendit_hosted_payment_id', $hostedPaymentId);
                        $payment->setAdditionalInformation('xendit_hosted_payment_token', $hostedPaymentToken);
                    }

                    // redirect to hosted payment page
                    $redirect = "https://tpi-ui.xendit.co/hosted-payments/$hostedPaymentId?hp_token=$hostedPaymentToken";
                    $resultRedirect = $this->getRedirectFactory()->create();
                    $resultRedirect->setUrl($redirect);
                    
                    return $resultRedirect;
                } else {
                    $message = 'Error connecting to Xendit. Please check your API key.';
                    
                    return $this->processFailedPayment($incrementIds, $message);
                }
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

    private function requestHostedPayment($requestData)
    {
        $hostedPaymentUrl = $this->getDataHelper()->getCheckoutUrl() . "/payment/xendit/hosted-payments";
        $hostedPaymentMethod = \Zend\Http\Request::METHOD_POST;

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

    private function calculatePromo($order)
    {
        $promo = [];
        $ruleIds = $order->getAppliedRuleIds();
        $enabledPromotions = $this->getDataHelper()->getEnabledPromo();

        if (empty($ruleIds) || empty($enabledPromotions)) {
            return $promo;
        }

        $ruleIds = explode(',', $ruleIds);
        $rawAmount = ceil($order->getSubtotal() + $order->getShippingAmount());

        foreach ($ruleIds as $ruleId) {
            foreach ($enabledPromotions as $promotion) {
                if ($promotion['rule_id'] === $ruleId) {
                    $rule = $this->getRuleRepository()->getById($ruleId);
                    $promo[] = $this->constructPromo($rule, $promotion, $rawAmount);
                }
            }
        }

        if (!empty($promo)) {
            $args = [];
            $args['promotions'] = json_encode($promo);
            $args['amount'] = $rawAmount;

            return $args;
        }

        return $promo;
    }

    private function constructPromo($rule, $promotion, $rawAmount)
    {
        $constructedPromo = [
            'bin_list' => $promotion['bin_list'],
            'title' => $rule->getName(),
            'promo_reference' => $rule->getRuleId(),
            'type' => $this->getDataHelper()->mapSalesRuleType($rule->getSimpleAction()),
        ];
        $rate = $rule->getDiscountAmount();

        switch ($rule->getSimpleAction()) {
            case 'to_percent':
                $rate = 1 - ($rule->getDiscountAmount() / 100);
                break;
            case 'by_percent':
                $rate = ($rule->getDiscountAmount() / 100);
                break;
            case 'to_fixed':
                $rate = (int)$rawAmount - $rule->getDiscountAmount();
                break;
            case 'by_fixed':
                $rate = (int)$rule->getDiscountAmount();
                break;
        }

        $constructedPromo['rate'] = $rate;

        return $constructedPromo;
    }
    
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
                return $this->processSuccessfulPayment($orders, $payment, $charge);
            } else {
                return $this->processFailedPayment($orderIds, $charge['failure_reason']);
            }
        }

        return $this->processFailedPayment($orderIds);
    }

    private function processFailedPayment($orderIds, $failureReason = 'Unexpected Error with empty charge')
    {
        $this->getCheckoutHelper()->restoreQuote();

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

    private function processSuccessfulPayment($orders, $payment, $charge)
    {
        $transactionId = $charge['id'];
        $payment->setTransactionId($transactionId);
        $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE, null, true);

        foreach ($orders as $key => $order) {
            $orderState = Order::STATE_PROCESSING;

            $order->setState($orderState)
                ->setStatus($orderState)
                ->addStatusHistoryComment("Xendit payment completed. Transaction ID: $transactionId");

            $payment = $order->getPayment();
            $payment->setTransactionId($transactionId);
            $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE, null, true);

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
