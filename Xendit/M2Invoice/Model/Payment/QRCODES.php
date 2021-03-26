<?php

namespace Xendit\M2Invoice\Model\Payment;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Framework\Webapi\Exception;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;
use Zend\Http\Request;

/**
 * Class QRCODES
 * @package Xendit\M2Invoice\Model\Payment
 */
class QRCODES extends AbstractInvoice
{
    protected $_code        = 'qr_codes';
    protected $methodCode   = 'QRCODES';
    protected $type         = 'DYNAMIC';

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

        if ($amount < $this->dataHelper->getQrCodesMinOrderAmount() || $amount > $this->dataHelper->getQrCodesMaxOrderAmount()) {
            return false;
        }

        if(!$this->dataHelper->getQrCodesActive()){
            return false;
        }

        return true;
    }

    /**
     * @param InfoInterface $payment
     * @param float $amount
     * @return AbstractInvoice|void
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function authorize(InfoInterface $payment, $amount)
    {
        $order              = $payment->getOrder();
        $quoteId            = $order->getQuoteId();
        $quote              = $this->quoteRepository->get($quoteId);
        $orderId            = $order->getRealOrderId();

        $payment->setIsTransactionPending(true);
        if ($quote->getIsMultiShipping()) {
            return $this;
        }

        try {
            $args = [
                'external_id'   => $this->dataHelper->getExternalId($orderId),
                'type'          => $this->type,
                'description'   => $orderId,
                'callback_url'  => $this->getXenditCallbackUrl(), ///TODO: need to change this
                'amount'        => round($amount)
            ];

            // send Qrcode Payment request
            $qrcodePayment = $this->requestQrcodePayment($args);

            // handle '422' error
            if ( isset($qrcodePayment['error_code']) ) {
                // handle duplicate payment error
                if ($qrcodePayment['error_code'] == 'DUPLICATE_ERROR') {
                    $args = array_replace($args, [
                        'external_id' => $this->dataHelper->getExternalId($orderId, true)
                    ]);
                    // re-send Qrcode Payment request
                    $qrcodePayment = $this->requestQrcodePayment($args);

                    if (isset($qrcodePayment['error_code'])) {
                        $message = $this->errorHandler->mapQrcodeErrorCode($qrcodePayment['error_code']);
                        $this->processFailedPayment($payment, $message);

                        throw new LocalizedException(
                            new Phrase($message)
                        );
                    }
                }
            }
            // set additional info
            if (isset($qrcodePayment['external_id'])) {
                $payment->setAdditionalInformation('xendit_qrcode_external_id', $qrcodePayment['external_id']);
            }
            if (isset($qrcodePayment['qr_string'])) {
                $payment->setAdditionalInformation('xendit_qr_string', $qrcodePayment['qr_string']);
            }
            if (isset($qrcodePayment['type'])) {
                $payment->setAdditionalInformation('xendit_qrcode_type', $qrcodePayment['type']);
            }
            if (isset($qrcodePayment['status'])) {
                $payment->setAdditionalInformation('xendit_qrcode_status', $qrcodePayment['status']);
            }
            // set amount
            $payment->setAdditionalInformation('xendit_qrcode_amount', round($amount));
            // set is_multishipping
            $payment->setAdditionalInformation('xendit_qrcode_is_multishipping', false);

        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            throw new LocalizedException(
                new Phrase($errorMsg)
            );
        }
    }

    /**
     * @param $requestData
     * @return mixed
     * @throws \Exception
     */
    private function requestQrcodePayment($requestData)
    {
        $this->_logger->info(json_encode($requestData));

        $qrocodeUrl = $this->dataHelper->getCheckoutUrl() . "/qr_codes";
        $qrcodeMethod = Request::METHOD_POST;
        $options = [
            'timeout' => 60
        ];

        try {
            $qrcodePayment = $this->apiHelper->request(
                $qrocodeUrl,
                $qrcodeMethod,
                $requestData,
                false,
                null,
                $options,
                [
                    'x-api-version' => '2020-02-01'
                ]
            );
            $this->_logger->info(json_encode($qrcodePayment));
        } catch (\Exception $e) {
            $this->_logger->info(json_encode($e));
            throw $e;
        }

        return $qrcodePayment;
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
     * @param $requestData
     * @return mixed
     * @throws \Exception
     */
    public function simulateQrcodePayment($requestData)
    {
        $this->_logger->info(json_encode($requestData));

        $simulateUrl = $this->dataHelper->getCheckoutUrl() . "/qr_codes/" . $requestData['external_id'] . '/payments/simulate' ;
        $simulateMethod = Request::METHOD_POST;
        $options = [
            'timeout' => 60
        ];
        try {
            $simulatePayment = $this->apiHelper->request(
                $simulateUrl,
                $simulateMethod,
                $requestData,
                false,
                null,
                $options,
                [
                    'x-api-version' => '2020-02-01'
                ]
            );
            $this->_logger->info(json_encode($simulatePayment));
        } catch (\Exception $e) {
            $this->_logger->info(json_encode($e));
            throw $e;
        }

        return $simulatePayment;
    }

    public function checkOrder($order, $callbackPayload)
    {
        if (isset($callbackPayload['id'])) {
            $transactionId = $callbackPayload['id'];
        }
        if (!$order->canInvoice()) {
            $result = [
                'status_code'   => 'success',
                'status'        => __('SUCCESS'),
                'message'       => 'Order is already processed'
            ];

            return $result;
        }
        $this->logger->debug(["checkOrder"]);

        if (isset($callbackPayload['status'])) {
            $paymentStatus = $callbackPayload['status'];
        }
        $this->logger->debug(['payment status: ' => $paymentStatus]);

        $statusList = [
            'COMPLETED'
        ];
        if (in_array($paymentStatus, $statusList)) {
            $orderState = Order::STATE_PROCESSING;

            if ($transactionId) {
                $order->setState($orderState)
                      ->setStatus($orderState)
                      ->addStatusHistoryComment("Xendit payment completed. Transaction ID: $transactionId");

            } else {
                $order->setState($orderState)
                      ->setStatus($orderState)
                      ->addStatusHistoryComment("Xendit payment completed.");
            }

            $payment = $order->getPayment();
            $payment->setTransactionId($transactionId);
            $payment->addTransaction(Transaction::TYPE_CAPTURE, null, true);
            // payment save
            $payment->save();
            // order save
            $order->save();
            // invoice order
            $this->invoiceOrder($order, $transactionId);

            $result = [
                'status_code'   => 'success',
                'status'        => __('SUCCESS'),
                'message'       => ('Invoice paid')
            ];
        } else {
            //FAILED or EXPIRED
            $orderState = Order::STATE_CANCELED;

            if ($order->getStatus() != $orderState) {
                $this->checkoutHelper->cancelCurrentOrder(
                    "Order #".($order->getId())." was rejected by Xendit. Transaction #$transactionId."
                );
                //restore cart
                $this->checkoutHelper->restoreQuote();
            }

            $order->addStatusHistoryComment("Xendit payment " . strtolower($paymentStatus) . ". Transaction ID: $transactionId")
                  ->save();

            $result = [
                'status_code'   => 'failed',
                'status'        => __('FAILED'),
                'message'       => ('Invoice not paid')
            ];
        }

        return $result;
    }

    /**
     * @param $order
     * @param $transactionId
     * @throws LocalizedException
     */
    public function invoiceOrder($order, $transactionId)
    {
        $this->logger->debug(["invoiceOrder"]);
        if (!$order->canInvoice()) {
            throw new LocalizedException(
                __('Cannot create an invoice.')
            );
        }
        // prepare invoice
        $invoice = $this->invoiceService->prepareInvoice($order);

        if (!$invoice->getTotalQty()) {
            throw new LocalizedException(
                __('You can\'t create an invoice without products.')
            );
        }

        /*
         * Look Magento/Sales/Model/Order/Invoice.register() for CAPTURE_OFFLINE explanation.
         * Basically, if !config/can_capture and config/is_gateway and CAPTURE_OFFLINE and
         * Payment.IsTransactionPending => pay (Invoice.STATE = STATE_PAID...)
	    */
        if ($transactionId) {
            $invoice->setTransactionId($transactionId);
        }
        $invoice->setRequestedCaptureCase(Order\Invoice::CAPTURE_OFFLINE);
        $invoice->register();
        $transaction = $this->dbTransaction->addObject($invoice)->addObject($invoice->getOrder());
        $transaction->save();
    }

    /**
     * @param $orderId
     * @return Order|null
     */
    public function getOrderById($orderId)
    {
        $order = $this->orderFactory->create()->loadByIncrementId($orderId);
        if (!$order->getId()) {
            $order = $this->orderFactory->create()->load($orderId);
            if (!$order->getId()) {
                return null;
            }
        }
        return $order;
    }

    public function checkQrCodeStatus($requestData)
    {
        $response = '';
        $url = $this->dataHelper->getCheckoutUrl() . "/qr_codes/" . $requestData['externalId'];
        $method = Request::METHOD_GET;
        $options = [
            'timeout' => 60
        ];

        if (isset($requestData['externalId'])) {
            try {
                $response = $this->apiHelper->request(
                    $url,
                    $method,
                    $requestData,
                    false,
                    null,
                    $options,
                    [
                        'x-api-version' => '2020-02-01'
                    ]
                );
                $this->_logger->info(json_encode($response));
            } catch (\Exception $e) {
                $this->_logger->info(json_encode($e));
                throw $e;
            }
        }

        return $response;
    }
}
