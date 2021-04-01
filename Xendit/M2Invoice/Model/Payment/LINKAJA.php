<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Framework\Phrase;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Framework\Exception\LocalizedException;
use Zend\Http\Request;

class LINKAJA extends AbstractInvoice
{
    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'linkaja';
    protected $methodCode = 'LINKAJA';

    /**
     * @var \Xendit\M2Invoice\Logger\Logger
     */
    protected $_logger;

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

        $items = [];
        foreach ($quote->getItems() as $item) {
            $items[] = [
                'id' => $item->getId(),
                'name' => $item->getName(),
                'price' => $item->getPrice(),
                'quantity' => $item->getQty()
            ];
        }

        $orderId = $order->getRealOrderId();
        try {
            $args = [
                'external_id' => $this->dataHelper->getExternalId($orderId),
                'amount' => $amount,
                'items' => $items,
                'description'   => $orderId,
                'platform_callback_url' => $this->getXenditCallbackUrl(),
                'callback_url' => $this->getXenditCallbackUrl(),
                'redirect_url' => $this->dataHelper->getSuccessUrl(false) . '?external_id=' . $this->dataHelper->getExternalId($orderId) . '&ewallet_type=' . strtoupper($this->methodCode) . '&payment_type=EWALLET&order_id=' . $orderId,
                'ewallet_type' => $this->methodCode,
            ];

            if (isset($additionalData['phone_number'])) {
                $args['phone'] = $additionalData['phone_number'];
            }

            $ewalletPayment = $this->requestEwalletPayment($args);

            if (isset($ewalletPayment['error_code'])) {
                if ($ewalletPayment['error_code'] == 'DUPLICATE_PAYMENT_ERROR') {
                    $args = array_replace($args, [
                        'external_id' => $this->dataHelper->getExternalId($orderId, true)
                    ]);
                    $ewalletPayment = $this->requestEwalletPayment($args);
                }

                if (isset($ewalletPayment['error_code'])) {
                    $message = $this->errorHandler->mapLinkajaErrorCode($ewalletPayment['error_code']);
                    $this->processFailedPayment($payment, $message);
                }
            }
            if (isset($ewalletPayment['external_id'])) {
                $payment->setAdditionalInformation('xendit_linkaja_external_id', $ewalletPayment['external_id']);
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
        $ewalletUrl = $this->dataHelper->getCheckoutUrl() . "/payment/xendit/ewallets";
        $ewalletMethod = Request::METHOD_POST;
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

        if ($amount < $this->dataHelper->getLinkajaMinOrderAmount() || $amount > $this->dataHelper->getLinkajaMaxOrderAmount()) {
            return false;
        }

        if(!$this->dataHelper->getLinkajaActive()){
            return false;
        }

        return true;
    }
}
