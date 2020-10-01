<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\Phrase;
use Xendit\M2Invoice\Enum\LogDNALevel;
use Xendit\M2Invoice\Helper\ApiRequest;
use Xendit\M2Invoice\Helper\Checkout;
use Xendit\M2Invoice\Helper\Data;
use Xendit\M2Invoice\Helper\LogDNA;

class Notification extends Action
{
    private $jsonResultFactory;

    private $checkoutHelper;

    private $orderFactory;

    private $dataHelper;

    private $apiHelper;

    private $logDNA;

    public function __construct(
        Context $context,
        JsonFactory $jsonResultFactory,
        Checkout $checkoutHelper,
        OrderFactory $orderFactory,
        Data $dataHelper,
        ApiRequest $apiHelper,
        LogDNA $logDNA
    ) {
        parent::__construct($context);
        $this->jsonResultFactory = $jsonResultFactory;
        $this->checkoutHelper = $checkoutHelper;
        $this->orderFactory = $orderFactory;
        $this->dataHelper = $dataHelper;
        $this->apiHelper = $apiHelper;
        $this->logDNA = $logDNA;
    }

    public function execute()
    {
        try {
            $post = $this->getRequest()->getContent();
            $callbackToken = $this->getRequest()->getHeader('X-CALLBACK-TOKEN');
            $callbackPayload = json_decode($post, true);

            if (!empty($callbackToken)) {
                $result = $this->jsonResultFactory->create();
                /** You may introduce your own constants for this custom REST API */
                $result->setHttpResponseCode(\Magento\Framework\Webapi\Exception::HTTP_FORBIDDEN);
                $result->setData([
                    'status' => __('ERROR'),
                    'message' => 'Unauthorized callback request'
                ]);

                return $result;
            }

            if (!empty($callbackPayload['invoice_url'])) {
                return $this->handleInvoiceCallback($callbackPayload);
            } else {
                return $this->handleEwalletCallback($callbackPayload);
            }
        } catch (\Exception $e) {
            $message = "Error invoice callback: " . $e->getMessage();
            $this->logDNA->log(LogDNALevel::ERROR, $message, $callbackPayload);

            $result = $this->jsonResultFactory->create();
            /** You may introduce your own constants for this custom REST API */
            $result->setHttpResponseCode(\Magento\Framework\Webapi\Exception::HTTP_BAD_REQUEST);
            $result->setData([
                    'status' => __('ERROR'),
                    'message' => $message
                ]);

            return $result;
        }
    }

    public function handleInvoiceCallback($callbackPayload) {
        if (!isset($callbackPayload['description']) || !isset($callbackPayload['id'])) {
            $result = $this->jsonResultFactory->create();
            /** You may introduce your own constants for this custom REST API */
            $result->setHttpResponseCode(\Magento\Framework\Webapi\Exception::HTTP_BAD_REQUEST);
            $result->setData([
                'status' => __('ERROR'),
                'message' => 'Callback body is invalid'
            ]);

            return $result;
        }

        // Invoice description is Magento's order ID
        $description = $callbackPayload['description'];
        // in case of multishipping, we separate order IDs with `-`
        $orderIds = explode("-", $description);

        $transactionId = $callbackPayload['id'];
        $isMultishipping = (count($orderIds) > 1) ? true : false;

        $invoice = $this->getXenditInvoice($transactionId);

        if( $isMultishipping ) {
            foreach ($orderIds as $key => $value) {
                $order = $this->orderFactory->create();
                $order->load($value);

                $result = $this->checkOrder($order, false, $callbackPayload, $invoice, $description);
            }
            
            return $result;
        } else {
            $order = $this->getOrderById($description);

            if (!$order) {
                $order = $this->orderFactory->create();
                $order->load($description);
            }

            return $this->checkOrder($order, false, $callbackPayload, $invoice, $description);
        }
    }

    public function handleEwalletCallback($callbackPayload) {
        if (!$callbackPayload['external_id']) {
            $result = $this->jsonResultFactory->create();
            /** You may introduce your own constants for this custom REST API */
            $result->setHttpResponseCode(\Magento\Framework\Webapi\Exception::HTTP_BAD_REQUEST);
            $result->setData([
                'status' => __('ERROR'),
                'message' => 'Callback external_id is invalid'
            ]);

            return $result;
        }

        $failureCode = 'UNKNOWN_ERROR';
        if (isset($callbackPayload['failure_code'])) {
            $failureCode = $callbackPayload['failure_code'];
        }

        $extIdPrefix = $this->dataHelper->getExternalIdPrefix();
        // Trimmed external ID from prefix is Magento's order ID
        $orderId = ltrim($callbackPayload['external_id'], $extIdPrefix);
        $order = $this->getOrderById($orderId);

        return $this->checkOrder($order, true, $callbackPayload, null, $orderId);
    }

    private function checkOrder($order, $isEwallet, $callbackPayload, $invoice, $callbackDescription) {
        $transactionId = $callbackPayload['id'];

        if (!$order) {
            $result = $this->jsonResultFactory->create();
            /** You may introduce your own constants for this custom REST API */
            $result->setHttpResponseCode(\Magento\Framework\Webapi\Exception::HTTP_BAD_REQUEST);
            $result->setData([
                'status' => __('ERROR'),
                'message' => 'Order not found'
            ]);

            return $result;
        }

        if (!$order->canInvoice()) {
            $result = $this->jsonResultFactory->create();
            $result->setData([
                'status' => __('SUCCESS'),
                'message' => 'Order is already processed'
            ]);

            return $result;
        }

        if ($isEwallet) {
            $paymentStatus = $this->getEwalletStatus($callbackPayload['ewallet_type'], $callbackPayload['external_id']);
        } else {
            $paymentStatus = $invoice['status'];

            if ($invoice['description'] !== $callbackDescription) {
                $result = $this->jsonResultFactory->create();
                /** You may introduce your own constants for this custom REST API */
                $result->setHttpResponseCode(\Magento\Framework\Webapi\Exception::HTTP_BAD_REQUEST);
                $result->setData([
                    'status' => __('ERROR'),
                    'message' => 'Invoice is not for this order'
                ]);

                return $result;
            }
        }

        $statusList = array('PAID', 'SETTLED', 'COMPLETED');
        if (in_array($paymentStatus, $statusList)) {
            $orderState = Order::STATE_PROCESSING;

            $order->setState($orderState)
                ->setStatus($orderState)
                ->addStatusHistoryComment("Xendit payment completed. Transaction ID: $transactionId");

            $payment = $order->getPayment();
            $payment->setTransactionId($transactionId);
            $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE, null, true);

            if ($isEwallet) {
                $payment->setAdditionalInformation('xendit_ewallet_id', $transactionId);
                $payment->save();
            }

            $order->save();

            $this->invoiceOrder($order, $transactionId);

            $result = $this->jsonResultFactory->create();
            $result->setData([
                'status' => __('SUCCESS'),
                'message' => ($isEwallet ? 'eWallet paid' : 'Invoice paid')
            ]);
        } else { //FAILED or EXPIRED
            $orderState = Order::STATE_CANCELED;

            if ($order->getStatus() != $orderState) {
                $this->getCheckoutHelper()->cancelCurrentOrder(
                    "Order #".($order->getId())." was rejected by Xendit. Transaction #$transactionId."
                );
                $this->getCheckoutHelper()->restoreQuote(); //restore cart
            }

            if ($isEwallet) {
                $order  ->setState($orderState)
                        ->setStatus($orderState);
                $order  ->save();

                $payment = $order->getPayment();
                $payment->setAdditionalInformation('xendit_ewallet_failure_code', $failureCode);
                $payment->save();
            }

            $result = $this->jsonResultFactory->create();
            $result->setData([
                'status' => __('FAILED'),
                'message' => ($isEwallet ? 'eWallet not paid' : 'Invoice not paid')
            ]);
        }

        return $result;
    }

    private function getXenditInvoice($invoiceId)
    {
        $invoiceUrl = $this->dataHelper->getCheckoutUrl() . "/payment/xendit/invoice/$invoiceId";
        $invoiceMethod = \Zend\Http\Request::METHOD_GET;

        try {
            $invoice = $this->apiHelper->request($invoiceUrl, $invoiceMethod);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            throw new \Magento\Framework\Exception\LocalizedException(
                new Phrase($e->getMessage())
            );
        }

        return $invoice;
    }

    private function getEwalletStatus($ewalletType, $externalId)
    {
        $ewalletUrl = $this->dataHelper->getCheckoutUrl() . "/payment/xendit/ewallets?ewallet_type=".$ewalletType."&external_id=".$externalId;
        $ewalletMethod = \Zend\Http\Request::METHOD_GET;

        try {
            $response = $this->apiHelper->request($ewalletUrl, $ewalletMethod);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            throw new \Magento\Framework\Exception\LocalizedException(
                new Phrase($e->getMessage())
            );
        }

        if ($ewalletType == 'DANA') {
            $response['status'] = $response['payment_status'];
        }

        $statusList = array("COMPLETED", "PAID", "SUCCESS_COMPLETED"); //OVO, DANA, LINKAJA
        if (in_array($response['status'], $statusList)) {
            return "COMPLETED";
        }
        
        return $response['status'];
    }

    private function invoiceOrder($order, $transactionId)
    {
        if (!$order->canInvoice()) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Cannot create an invoice.')
            );
        }
        
        $invoice = $this->getObjectManager()
            ->create('Magento\Sales\Model\Service\InvoiceService')
            ->prepareInvoice($order);
        
        if (!$invoice->getTotalQty()) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('You can\'t create an invoice without products.')
            );
        }
        
        /*
         * Look Magento/Sales/Model/Order/Invoice.register() for CAPTURE_OFFLINE explanation.
         * Basically, if !config/can_capture and config/is_gateway and CAPTURE_OFFLINE and
         * Payment.IsTransactionPending => pay (Invoice.STATE = STATE_PAID...)
         */
        $invoice->setTransactionId($transactionId);
        $invoice->setRequestedCaptureCase(Order\Invoice::CAPTURE_OFFLINE);
        $invoice->register();
        $transaction = $this->getObjectManager()->create('Magento\Framework\DB\Transaction')
            ->addObject($invoice)
            ->addObject($invoice->getOrder());
        $transaction->save();
    }

    protected function getCheckoutHelper()
    {
        return $this->checkoutHelper;
    }

    protected function getObjectManager()
    {
        return \Magento\Framework\App\ObjectManager::getInstance();
    }

    protected function getOrderById($orderId)
    {
        $order = $this->orderFactory->create()->loadByIncrementId($orderId);
        if (!$order->getId()) {
            return null;
        }
        return $order;
    }
}
