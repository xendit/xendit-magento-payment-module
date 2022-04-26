<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Framework\Phrase;
use Magento\Payment\Model\InfoInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Class CCSubscription
 * @package Xendit\M2Invoice\Model\Payment
 */
class CCSubscription extends AbstractInvoice
{
    const PLATFORM_NAME = 'MAGENTO2';
    const PAYMENT_TYPE = 'CREDIT_CARD_SUBSCRIPTION';
    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'cc_subscription';
    protected $methodCode = 'CC_SUBSCRIPTION';
    protected $_canRefund = true;

    /**
     * @param InfoInterface $payment
     * @param float $amount
     * @return $this|AbstractInvoice
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function authorize(InfoInterface $payment, $amount)
    {
        $payment->setIsTransactionPending(true);

        $order = $payment->getOrder();
        $quoteId = $order->getQuoteId();
        $quote = $this->quoteRepository->get($quoteId);

        if ($quote->getIsMultiShipping() ||
            $quote->getPayment()->getAdditionalInformation('xendit_is_subscription')
        ) {
            return $this;
        }

        try {
            if (!$this->customerSession->isLoggedIn()) {
                $message = 'You must logged in to use this payment method';
                throw new LocalizedException(
                    new Phrase($message)
                );
            }

            $orderId = $order->getRealOrderId();

            $billingAddress = $order->getBillingAddress();
            $shippingAddress = $order->getShippingAddress();

            $firstName = $billingAddress->getFirstname() ?: $shippingAddress->getFirstname();
            $country = $billingAddress->getCountryId() ?: $shippingAddress->getCountryId();

            $rawAmount = ceil($order->getSubtotal() + $order->getShippingAmount());

            $args = array(
                'order_number' => $orderId,
                'currency' => $order->getBaseCurrencyCode(),
                'amount' => $amount,
                'payment_type' => self::PAYMENT_TYPE,
                'store_name' => $this->storeManager->getStore()->getName(),
                'platform_name' => self::PLATFORM_NAME,
                'platform_callback_url' => $this->dataHelper->getCCCallbackUrl(),
                'success_redirect_url' => $this->dataHelper->getSuccessUrl(),
                'failure_redirect_url' => $this->dataHelper->getFailureUrl($orderId),
                'is_subscription' => "true",
                'subscription_callback_url' => $this->dataHelper->getXenditSubscriptionCallbackUrl(),
                'payer_email' => $billingAddress->getEmail(),
                'subscription_option' => json_encode(array(
                    'interval' => $this->dataHelper->getCcSubscriptionInterval(),
                    'interval_count' => $this->dataHelper->getCcSubscriptionIntervalCount(),
                ), JSON_FORCE_OBJECT)
            );

            $promo = $this->calculatePromo($order, $rawAmount);

            if (!empty($promo)) {
                $args['promotions'] = json_encode($promo);
                $args['amount'] = $rawAmount;

                $invalidDiscountAmount = $order->getBaseDiscountAmount();
                $order->setBaseDiscountAmount(0);
                $order->setBaseGrandTotal($order->getBaseGrandTotal() - $invalidDiscountAmount);

                $invalidDiscountAmount = $order->getDiscountAmount();
                $order->setDiscountAmount(0);
                $order->setGrandTotal($order->getGrandTotal() - $invalidDiscountAmount);

                $order->setBaseTotalDue($order->getBaseGrandTotal());
                $order->setTotalDue($order->getGrandTotal());

                $payment->setBaseAmountOrdered($order->getBaseGrandTotal());
                $payment->setAmountOrdered($order->getGrandTotal());

                $payment->setAmountAuthorized($order->getGrandTotal());
                $payment->setBaseAmountAuthorized($order->getBaseGrandTotal());
            }

            $hostedPayment = $this->requestHostedPayment($args);

            if (isset($hostedPayment['error_code'])) {
                $message = isset($hostedPayment['message']) ? $hostedPayment['message'] : $hostedPayment['error_code'];
                $this->processFailedPayment($payment, $message);

                throw new LocalizedException(
                    new Phrase($message)
                );
            } else if (isset($hostedPayment['id'])) {
                $hostedPaymentId = $hostedPayment['id'];
                $hostedPaymentToken = $hostedPayment['hp_token'];

                $payment->setAdditionalInformation('xendit_hosted_payment_id', $hostedPaymentId);
                $payment->setAdditionalInformation('xendit_hosted_payment_token', $hostedPaymentToken);
                $payment->setAdditionalInformation('xendit_redirect_url', $this->dataHelper->getUiUrl()."/hosted-payments/".$hostedPaymentId."?hp_token=".$hostedPaymentToken);
            } else {
                $message = 'Error connecting to Xendit. Check your API key';
                $this->processFailedPayment($payment, $message);

                throw new LocalizedException(
                    new Phrase($message)
                );
            }
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            throw new LocalizedException(
                new Phrase($errorMsg)
            );
        }

        return $this;
    }

    /**
     * @param InfoInterface $payment
     * @param float $amount
     * @return $this|AbstractInvoice
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function refund(InfoInterface $payment, $amount)
    {
        $chargeId = $payment->getAdditionalInformation('xendit_charge_id');

        if ($chargeId) {
            $order = $payment->getOrder();
            $orderId = $order->getRealOrderId();
            $canRefundMore = $payment->getCreditmemo()->getInvoice()->canRefund();
            $isFullRefund = !$canRefundMore &&
                0 == (double)$order->getBaseTotalOnlineRefunded() + (double)$order->getBaseTotalOfflineRefunded();


            $refundData = [
                'amount' => $this->getCurrency() == 'IDR' ? $this->dataHelper->truncateDecimal($amount) : $amount,
                'external_id' => $this->dataHelper->getExternalId($orderId, true)
            ];
            $refund = $this->requestRefund($chargeId, $refundData);

            $this->handleRefundResult($payment, $refund, $canRefundMore);

            return $this;
        } else {
            throw new LocalizedException(
                __("Refund not available because there is no capture")
            );
        }
    }

    /**
     * @param $chargeId
     * @param $requestData
     * @return mixed
     * @throws \Exception
     */
    private function requestRefund($chargeId, $requestData)
    {
        $refundUrl = $this->dataHelper->getCheckoutUrl() . "/payment/xendit/credit-card/charges/$chargeId/refund";
        $refundMethod = \Zend\Http\Request::METHOD_POST;

        try {
            $refund = $this->apiHelper->request($refundUrl, $refundMethod, $requestData);
        } catch (\Exception $e) {
            throw $e;
        }

        return $refund;
    }

    /**
     * @param $payment
     * @param $refund
     * @param $canRefundMore
     * @throws LocalizedException
     */
    private function handleRefundResult($payment, $refund, $canRefundMore)
    {
        if (isset($refund['error_code'])) {
            throw new LocalizedException(
                __($refund['message'])
            );
        }

        if ($refund['status'] == 'FAILED') {
            throw new LocalizedException(
                __('Refund failed, please check Xendit dashboard')
            );
        }

        $payment->setTransactionId(
            $refund['id']
        )->setIsTransactionClosed(
            1
        )->setShouldCloseParentTransaction(
            !$canRefundMore
        );
    }

    /**
     * @param $requestData
     * @return mixed
     * @throws \Exception
     */
    public function requestHostedPayment($requestData)
    {
        $hostedPaymentUrl = $this->dataHelper->getCheckoutUrl() . "/payment/xendit/hosted-payments";
        $hostedPaymentMethod = \Zend\Http\Request::METHOD_POST;

        try {
            $hostedPayment = $this->apiHelper->request(
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
     * @param $order
     * @param $rawAmount
     * @return array
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function calculatePromo($order, $rawAmount)
    {
        $promo = [];
        $ruleIds = $order->getAppliedRuleIds();
        $enabledPromotions = $this->dataHelper->getEnabledPromo();

        if (empty($ruleIds) || empty($enabledPromotions)) {
            return $promo;
        }

        $ruleIds = explode(',', $ruleIds);

        foreach ($ruleIds as $ruleId) {
            foreach ($enabledPromotions as $promotion) {
                if ($promotion['rule_id'] === $ruleId) {
                    $rule = $this->ruleRepo->getById($ruleId);
                    $promo[] = $this->constructPromo($rule, $promotion, $rawAmount);
                }
            }
        }

        return $promo;
    }

    /**
     * @param $rule
     * @param $promotion
     * @param $rawAmount
     * @return array
     */
    private function constructPromo($rule, $promotion, $rawAmount)
    {
        $constructedPromo = [
            'bin_list'        => $promotion['bin_list'],
            'title'           => $rule->getName(),
            'promo_reference' => $rule->getRuleId(),
            'type'            => $this->dataHelper->mapSalesRuleType($rule->getSimpleAction()),
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

    /**
     * @param $payment
     * @param $message
     */
    public function processFailedPayment($payment, $message)
    {
        $payment->setAdditionalInformation('xendit_failure_reason', $message);
    }
}
