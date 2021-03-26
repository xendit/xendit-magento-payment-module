<?php

declare(strict_types=1);

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Framework\Phrase;
use Magento\Payment\Model\InfoInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Class Dana
 * @package Xendit\M2Invoice\Model\Payment
 */
class Dana extends AbstractInvoice
{
    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'dana';
    protected $_minAmount = 1;
    protected $_maxAmount = 10000000;
    protected $methodCode = 'DANA';

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
        $additionalData = $this->getAdditionalData();

        $order = $payment->getOrder();
        $quoteId = $order->getQuoteId();
        $quote = $this->quoteRepository->get($quoteId);

        if ($quote->getIsMultiShipping()) {
            return $this;
        }

        $orderId = $order->getRealOrderId();
        try {
            $args = [
                'external_id' => $this->dataHelper->getExternalId($orderId),
                'amount' => round($amount),
                'description'   => $orderId,
                'platform_callback_url' => $this->getXenditCallbackUrl(),
                'callback_url' => $this->getXenditCallbackUrl(),
                'redirect_url' => $this->dataHelper->getSuccessUrl(false) . '?external_id=' . $this->dataHelper->getExternalId($orderId) . '&ewallet_type=' . strtoupper($this->methodCode) . '&payment_type=EWALLET&order_id=' . $orderId,
                'ewallet_type' => $this->methodCode
            ];

            if (isset($additionalData['phone_number'])) {
                $args['phone'] = $additionalData['phone_number'];
            }

            $expireDate = $this->dataHelper->getDanaExpirationDate();
            if ($expireDate) {
            	$args['expiration_date'] = $expireDate;
            }


            $ewalletPayment = $this->requestEwalletPayment($args);

            if ( isset($ewalletPayment['error_code']) ) {
                if ($ewalletPayment['error_code'] == 'DUPLICATE_ERROR') {
                    $args = array_replace($args, array(
                        'external_id' => $this->dataHelper->getExternalId($orderId, true)
                    ));
                    $ewalletPayment = $this->requestEwalletPayment($args);
                }

                if (isset($ewalletPayment['error_code'])) {
                    $message = $this->errorHandler->mapDanaErrorCode($ewalletPayment['error_code']);
                    $this->processFailedPayment($payment, $message);
                }
            }

            if (isset($ewalletPayment['external_id'])) {
                $payment->setAdditionalInformation('xendit_dana_external_id', $ewalletPayment['external_id']);
            }
            if (isset($ewalletPayment['checkout_url'])) {
                $payment->setAdditionalInformation('xendit_redirect_url', $ewalletPayment['checkout_url']);
            }
            if (isset($ewalletPayment['amount'])) {
                $payment->setAdditionalInformation('amount', $ewalletPayment['amount']);
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
     * @param $requestData
     * @param bool $isRetried
     * @return mixed
     * @throws \Exception
     */
    private function requestEwalletPayment($requestData, $isRetried = true)
    {

        $this->xenditLogger->info(json_encode($requestData));

        $ewalletUrl = $this->dataHelper->getCheckoutUrl() . "/ewallets";
        $ewalletMethod = \Zend\Http\Request::METHOD_POST;
        $options = [
            'timeout' => 60
        ];

        try {
            $ewalletPayment = $this->apiHelper->request(
                $ewalletUrl,
                $ewalletMethod,
                $requestData,
                null,
                null,
                $options,
                [
                    'x-api-version' => '2020-02-01'
                ]
            );
            $this->xenditLogger->info(json_encode($ewalletPayment));
        } catch (\Exception $e) {
            $this->xenditLogger->info(json_encode($e));

            throw $e;
        }

        return $ewalletPayment;
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    private function getAdditionalData()
    {
        static $data = [];
        if (count($data) < 1) {
            $data = (array) $this->getPaymentMethod();
        }
        return $this->elementFromArray($data, 'additional_data');
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    private function getPaymentMethod()
    {
        /**
         * @var array $data
         * Holds submitted JSOn data in a PHP associative array
         */
        static $data = [];
        if (count($data) < 1) {
            $data = (array) $this->dataHelper->jsonData();
        }
        return $this->elementFromArray($data, 'paymentMethod');
    }

    /**
     * @param $data
     * @param $element
     * @return array
     */
    private function elementFromArray($data, $element)
    {
        $r = [];
        if (key_exists($element, $data)) {
            $r = (array) $data[$element];
        }
        return $r;
    }

    /**
     * @param $payment
     * @param $message
     */
    private function processFailedPayment($payment, $message)
    {
        $payment->setAdditionalInformation('xendit_failure_reason', $message);
    }

    /**
     * @param CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(CartInterface $quote = null)
    {
        if ($quote === null) {
            return false;
        }

        $amount = ceil($quote->getSubtotal() + $quote->getShippingAddress()->getShippingAmount());

        if ($amount < $this->_minAmount || $amount > $this->_maxAmount) {
            return false;
        }

        if(!$this->dataHelper->getDanaActive()){
            return false;
        }

        return true;

    }
}