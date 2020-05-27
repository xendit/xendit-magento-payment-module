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
            $method             = $this->getRequest()->getParam('method');

            $transactionAmount  = 0;
            $incrementIds       = [];
            $orderPayments      = [];

            foreach ($orderIds as $key => $value) {
                $order              = $this->getOrderFactory()->create();
                $order->load($value);

                $payment            = $order->getQuoteId();
                $orderPayments[]    = $payment;

                $quote = $this->getQuoteRepository()->get($payment);
    
                $transactionAmount  += (int)$order->getTotalDue();
                $incrementIds[]     = $order->getIncrementId();
    
                $id = $value;
            }

            //echo 'Total ' . $transactionAmount.'<br>'; print_r($orderPayments); echo "<br>";

            if ($method === 'cchosted') {
                $requestData        = [
                    'order_number'  => $rawOrderIds,
                    'amount'        => $transactionAmount,
                    'payment_type'  => 'CREDIT_CARD',
                    'store_name'    => $this->getStoreManager()->getStore()->getName(),
                    'platform_name' => 'MAGENTO2'
                ];

                $hostedPayment = $this->requestHostedPayment($requestData);

                if (isset($hostedPayment['error_code'])) {
                    $message = isset($hostedPayment['message']) ? $hostedPayment['message'] : $hostedPayment['error_code'];
                    //echo $message;exit;

                    foreach ($orderPayments as $key => $value) {
                        $quote = $this->getQuoteRepository()->get($value);
                        $payment = $quote->getPayment();
                        $this->processFailedPayment($payment, $message);
                    }
                    throw new \Magento\Framework\Exception\LocalizedException(
                        new Phrase($message)
                    );
                } elseif (isset($hostedPayment['id'])) {
                    $hostedPaymentId = $hostedPayment['id'];
                    $hostedPaymentToken = $hostedPayment['hp_token'];
                    //print_r($hostedPayment);
                    foreach ($orderPayments as $key => $value) {
                        $quote = $this->getQuoteRepository()->get($value);
                        $payment = $quote->getPayment();

                        $payment->setAdditionalInformation('xendit_hosted_payment_id', $hostedPaymentId);
                        $payment->setAdditionalInformation('xendit_hosted_payment_token', $hostedPaymentToken);
                        //var_dump($payment->getAdditionalInformation()); echo "~<br>";
                    }
                    header("Location: https://tpi-ui.xendit.co/hosted-payments/" . $hostedPaymentId);
                    exit();

                    //$resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
                    //$resultRedirect->setUrl('https://tpi-ui.xendit.co/hosted-payments/' . $hostedPaymentId, [ '_secure'=> false ]);
                } else {
                    $message = 'Error connecting to Xendit. Check your API key';
                    //echo $message;exit;
                    
                    foreach ($orderPayments as $key => $value) {
                        $quote = $this->getQuoteRepository()->get($value);
                        $payment = $quote->getPayment();
                        $this->processFailedPayment($payment, $message);
                    }
                    throw new \Magento\Framework\Exception\LocalizedException(
                        new Phrase($message)
                    );
                }
            }
            exit;
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

    private function processFailedPayment($payment, $message)
    {
        $payment->setAdditionalInformation('xendit_failure_reason', $message);
    }

    private function calculatePromo($order, $rawAmount)
    {
        $promo = [];
        $ruleIds = $order->getAppliedRuleIds();
        $enabledPromotions = $this->getDataHelper()->getEnabledPromo();

        if (empty($ruleIds) || empty($enabledPromotions)) {
            return $promo;
        }

        $ruleIds = explode(',', $ruleIds);

        foreach ($ruleIds as $ruleId) {
            foreach ($enabledPromotions as $promotion) {
                if ($promotion['rule_id'] === $ruleId) {
                    $rule = $this->getRuleRepository()->getById($ruleId);
                    $promo[] = $this->constructPromo($rule, $promotion, $rawAmount);
                }
            }
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
}
