<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Framework\Phrase;
use Zend\Http\Request;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\InfoInterface;

class KREDIVO extends AbstractInvoice
{
    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'kredivo';
    protected $methodCode = 'KREDIVO';

    /**
     * @param CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(CartInterface $quote = null)
    {
        if ($quote === null) {
            return false;
        }

        if ($this->getCurrency() != "IDR") {
            return false;
        }
        
        $amount = ceil($quote->getSubtotal() + $quote->getShippingAddress()->getShippingAmount());

        if ($amount < $this->dataHelper->getKredivoMinOrderAmount() || $amount > $this->dataHelper->getKredivoMaxOrderAmount()) {
            return false;
        }

        if(!$this->dataHelper->getKredivoActive()){
            return false;
        }

        if(empty($this->dataHelper->getKredivoCallbackAuthenticationToken())){
            return false;
        }

        return true;
    }

    /**
     * @param InfoInterface $payment
     * @param float $amount
     * @return $this|KREDIVO
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function authorize(InfoInterface $payment, $amount)
    {
        $payment->setIsTransactionPending(true);
        $additionalData = $this->getAdditionalData();

        $order               = $payment->getOrder();
        $orderItems          = $order->getAllItems();
        $quoteId             = $order->getQuoteId();
        $quote               = $this->quoteRepository->get($quoteId);
        $orderId             = $order->getRealOrderId();
        $items               = [];
        $customerDetails     = [];
        $shippingAddressData = [];

        if ($quote->getIsMultiShipping()) {
            return $this;
        }

        try {
            $shippingAddress = $order->getShippingAddress();

            // items details
            if ($orderItems) {
                foreach ($orderItems as $orderItem) {
                    $item = [];
                    $product = $orderItem->getProduct();
                    $item['id'] = $product->getId();
                    $item['name'] = $product->getName();
                    $item['price'] = $product->getPrice();
                    $item['type'] = $product->getTypeId(); // TODO; improve
                    $item['url'] = $product->getProductUrl();
                    $item['quantity'] = (int) $orderItem->getQtyOrdered();
                    $items[] = $item;
                }
            }
            // customer details
            $customerDetails['first_name'] = $order->getCustomerFirstname();
            $customerDetails['last_name'] = $order->getCustomerLastname();
            $customerDetails['email'] = $order->getCustomerEmail();
            $customerDetails['phone'] = $order->getShippingAddress()->getTelephone();
            // shipping address details
            $shippingAddressData['first_name'] = $shippingAddress->getData('firstname');
            $shippingAddressData['last_name'] = $shippingAddress->getData('lastname');
            $shippingAddressData['address'] = $shippingAddress->getData('street');
            $shippingAddressData['city'] = $shippingAddress->getData('city');
            $shippingAddressData['postal_code'] = $shippingAddress->getData('postcode');
            $shippingAddressData['phone'] =  $shippingAddress->getData('telephone');
            $shippingAddressData['country_code'] = 'IDN'; // TODO: make dynamic

            $args = [
                'cardless_credit_type'  => $this->methodCode,
                'external_id'           => $this->dataHelper->getExternalId($order->getId()),
                'amount'                => round($amount),
                'payment_type'          => '3_months',
                'items'                 => $items,
                'description'           => $order->getRealOrderId(),
                'customer_details'      => $customerDetails,
                'shipping_address'      => $shippingAddressData,
                'redirect_url'          => $this->dataHelper->getSuccessUrl(false),
                'callback_url'          => $this->dataHelper->getCheckoutUrl()."/payment/xendit/cardless-credit/callback",
                'platform_callback_url' => $this->getXenditCallbackUrl()
            ];

            if (isset($additionalData['xendit_payment_type'])) {
                $args['payment_type'] = $additionalData['xendit_payment_type'];
            }

            // send Cardless Credit Payment request
            $cardlessCreditPayment = $this->requestCardlessCreditPayment($args);

            // handle '422' error
            if ( isset($cardlessCreditPayment['error_code']) ) {
                // handle duplicate payment error
                if ($cardlessCreditPayment['error_code'] == 'DUPLICATE_PAYMENT_ERROR') {
                    $args = array_replace($args, [
                        'external_id' => $this->dataHelper->getExternalId($orderId, true)
                    ]);
                    // re-send Cardless Credit Payment request
                    $cardlessCreditPayment = $this->requestCardlessCreditPayment($args);
                }

                if (isset($cardlessCreditPayment['error_code'])) {
                    $message = $this->errorHandler->mapCardlessCreditErrorCode($cardlessCreditPayment['error_code']);
                    $this->processFailedPayment($payment, $message);

                    throw new LocalizedException(
                        new Phrase($message)
                    );
                }
            }
            // set additional info
            if (isset($cardlessCreditPayment['external_id'])) {
                $payment->setAdditionalInformation('xendit_external_id', $cardlessCreditPayment['external_id']);
            }
            if (isset($cardlessCreditPayment['redirect_url'])) {
                $payment->setAdditionalInformation('xendit_redirect_url', $cardlessCreditPayment['redirect_url']);
            }
            if (isset($cardlessCreditPayment['order_id'])) {
                $payment->setAdditionalInformation('xendit_order_id', $cardlessCreditPayment['order_id']);
            }
            if (isset($cardlessCreditPayment['cardless_credit_type'])) {
                $payment->setAdditionalInformation('xendit_cardless_credit_type', $cardlessCreditPayment['cardless_credit_type']);
            }

        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            throw new LocalizedException(
                new Phrase($errorMsg)
            );
        }
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
     * @param $requestData
     * @param bool $isRetried
     * @return mixed
     * @throws \Exception
     */
    private function requestCardlessCreditPayment($requestData, $isRetried = true)
    {
        $this->_logger->info(json_encode($requestData));

        $cardlessCreditUrl = $this->dataHelper->getCheckoutUrl() . "/payment/xendit/cardless-credit";
        $cardlessCreditMethod = Request::METHOD_POST;
        $options = [
            'timeout' => 60
        ];

        try {
            $cardlessCreditPayment = $this->apiHelper->request(
                $cardlessCreditUrl,
                $cardlessCreditMethod,
                $requestData,
                false,
                null,
                $options,
                [
                    'x-api-version' => '2020-02-01'
                ]
            );
            $this->_logger->info(json_encode($cardlessCreditPayment));
        } catch (\Exception $e) {
            $this->_logger->info(json_encode($e));
            throw $e;
        }

        return $cardlessCreditPayment;
    }

    /**
     * @param $payment
     * @param $message
     */
    private function processFailedPayment($payment, $message)
    {
        $payment->setAdditionalInformation('xendit_failure_reason', $message);
    }
}
