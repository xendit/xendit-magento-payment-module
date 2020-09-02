<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Phrase;
use Magento\Framework\Registry;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\Method\Logger;
use Xendit\M2Invoice\Helper\ApiRequest;
use Xendit\M2Invoice\Helper\LogDNA;
use Xendit\M2Invoice\Enum\LogDNALevel;

class CCInstallment extends CCHosted
{
    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'cc_installment';
    protected $_minAmount = 10000;
    protected $_maxAmount = 10000000;
    protected $_canRefund = true;
    protected $methodCode = 'CC_INSTALLMENT';

    /**
     * Override
     */
    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $payment->setIsTransactionPending(true);

        $order = $payment->getOrder();
        $quoteId = $order->getQuoteId();
        $quote = $this->quoteRepository->get($quoteId);

        if ($quote->getIsMultiShipping()) {
            return $this;
        }

        $orderId = $order->getRealOrderId();

        try {
            $rawAmount = ceil($order->getSubtotal() + $order->getShippingAmount());
            $args = [
                'order_number' => $orderId,
                'amount' => $amount,
                'payment_type' => self::PAYMENT_TYPE,
                'store_name' => $this->storeManager->getStore()->getName(),
                'platform_name' => self::PLATFORM_NAME,
                'is_installment' => true
            ];

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

                throw new \Magento\Framework\Exception\LocalizedException(
                    new Phrase($message)
                );
            } elseif (isset($hostedPayment['id'])) {
                $hostedPaymentId = $hostedPayment['id'];
                $hostedPaymentToken = $hostedPayment['hp_token'];

                $payment->setAdditionalInformation('xendit_hosted_payment_id', $hostedPaymentId);
                $payment->setAdditionalInformation('xendit_hosted_payment_token', $hostedPaymentToken);
            } else {
                $message = 'Error connecting to Xendit. Check your API key';
                $this->processFailedPayment($payment, $message);

                throw new \Magento\Framework\Exception\LocalizedException(
                    new Phrase($message)
                );
            }
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            throw new \Magento\Framework\Exception\LocalizedException(
                new Phrase($errorMsg)
            );
        }

        return $this;
    }
}
