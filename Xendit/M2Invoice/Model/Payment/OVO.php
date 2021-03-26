<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Framework\Phrase;
use Magento\Payment\Model\InfoInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Class OVO
 * @package Xendit\M2Invoice\Model\Payment
 */
class OVO extends AbstractInvoice
{
    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'ovo';
    protected $methodCode = 'OVO';

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
                'amount' => $amount,
                'ewallet_type' => $this->methodCode,
                'description'   => $orderId,
                'platform_callback_url' => $this->getXenditCallbackUrl()
            ];

            if (isset($additionalData['phone_number'])) {
                $args['phone'] = $additionalData['phone_number'];
            }

            $ewalletPayment = $this->requestEwalletPayment($args);

            if (isset($ewalletPayment['error_code'])) {
                if ($ewalletPayment['error_code'] == 'DUPLICATE_PAYMENT_REQUEST_ERROR') {
                    $args = array_replace($args, [
                        'external_id' => $this->dataHelper->getExternalId($orderId, true)
                    ]);
                    $ewalletPayment = $this->requestEwalletPayment($args);
                }

                if (isset($ewalletPayment['error_code'])) {
                    $message = $this->errorHandler->mapOvoErrorCode($ewalletPayment['error_code']);
                    $this->processFailedPayment($payment, $message);
                }
            }
            if(isset($ewalletPayment['external_id'])){
                $payment->setAdditionalInformation('xendit_ovo_external_id', $ewalletPayment['external_id']);
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
        } catch (\Exception $e) {
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

        if ($amount < $this->dataHelper->getOvoMinOrderAmount() || $amount > $this->dataHelper->getOvoMaxOrderAmount()) {
            return false;
        }

        if(!$this->dataHelper->getOvoActive()){
            return false;
        }

        return true;
    }
}
