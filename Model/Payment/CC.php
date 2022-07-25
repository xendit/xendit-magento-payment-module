<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Class CC
 * @package Xendit\M2Invoice\Model\Payment
 */
class CC extends AbstractInvoice
{
    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_isInitializeNeeded = true;

    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'cc';
    protected $methodCode = 'CREDIT_CARD';
    protected $_canRefund = true;

    /**
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param $amount
     * @return $this|CC
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Payment\Gateway\Http\ClientException
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $chargeId = $payment->getAdditionalInformation('xendit_charge_id');

        if ($chargeId) {
            $order = $payment->getOrder();
            $orderId = $order->getRealOrderId();
            $canRefundMore = $payment->getCreditmemo()->getInvoice()->canRefund();

            $refundData = [
                'amount' => $this->getCurrency() == 'IDR' ? $this->dataHelper->truncateDecimal($amount) : $amount,
                'external_id' => $this->dataHelper->getExternalId($orderId, true)
            ];
            $refund = $this->requestRefund($chargeId, $refundData);

            $this->handleRefundResult($payment, $refund, $canRefundMore);

            return $this;
        } else {
            throw new \Magento\Framework\Exception\LocalizedException(
                __("Refund not available because there is no capture")
            );
        }
    }

    /**
     * @param $payment
     * @param $refund
     * @param $canRefundMore
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function handleRefundResult($payment, $refund, $canRefundMore)
    {
        if (isset($refund['error_code'])) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __($refund['message'])
            );
        }

        if ($refund['status'] == 'FAILED') {
            throw new \Magento\Framework\Exception\LocalizedException(
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
     * @param $chargeId
     * @param $requestData
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Payment\Gateway\Http\ClientException
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
}
