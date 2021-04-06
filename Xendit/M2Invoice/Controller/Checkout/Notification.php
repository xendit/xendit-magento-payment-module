<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Xendit\M2Invoice\Helper\ApiRequest;
use Xendit\M2Invoice\Helper\Checkout;
use Xendit\M2Invoice\Helper\Data;
use Xendit\M2Invoice\Logger\Logger as XenditLogger;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\DB\Transaction as DbTransaction;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Framework\Webapi\Exception;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Zend\Http\Request;

/**
 * Class Notification
 * @package Xendit\M2Invoice\Controller\Checkout
 */
class Notification extends Action implements CsrfAwareActionInterface
{
    /**
     * @var JsonFactory
     */
    private $jsonResultFactory;

    /**
     * @var Checkout
     */
    private $checkoutHelper;

    /**
     * @var OrderFactory
     */
    private $orderFactory;

    /**
     * @var Data
     */
    private $dataHelper;

    /**
     * @var ApiRequest
     */
    private $apiHelper;

    /**
     * @var XenditLogger
     */
    private $logger;

    /**
     * @var DbTransaction
     */
    private $dbTransaction;

    /**
     * @var InvoiceService
     */
    private $invoiceService;

    /**
     * Notification constructor.
     * @param Context $context
     * @param JsonFactory $jsonResultFactory
     * @param Checkout $checkoutHelper
     * @param OrderFactory $orderFactory
     * @param Data $dataHelper
     * @param ApiRequest $apiHelper
     * @param XenditLogger $logger
     * @param DbTransaction $dbTransaction
     * @param InvoiceService $invoiceService
     */
    public function __construct(
        Context $context,
        JsonFactory $jsonResultFactory,
        Checkout $checkoutHelper,
        OrderFactory $orderFactory,
        Data $dataHelper,
        ApiRequest $apiHelper,
        XenditLogger $logger,
        DbTransaction $dbTransaction,
        InvoiceService $invoiceService
    ) {
        parent::__construct($context);
        $this->jsonResultFactory = $jsonResultFactory;
        $this->checkoutHelper = $checkoutHelper;
        $this->orderFactory = $orderFactory;
        $this->dataHelper = $dataHelper;
        $this->apiHelper = $apiHelper;
        $this->logger = $logger;
        $this->dbTransaction = $dbTransaction;
        $this->invoiceService = $invoiceService;
    }

    /**
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Json|\Magento\Framework\Controller\ResultInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute()
    {
        try {
            $post = $this->getRequest()->getContent();
            $callbackToken = $this->getRequest()->getHeader('X-CALLBACK-TOKEN');
            $callbackPayload = json_decode($post, true);

            $this->logger->info("callbackPayload");
            $this->logger->info($post);

            if (!empty($callbackPayload['invoice_url'])) {
                // Invoice payment (CC / Kredivo / Direct Debit BRI)
                return $this->handleInvoiceCallback($callbackPayload);
            } else {
                // E-wallet payment (OVO / DANA / LINKAJA)
                return $this->handleEwalletCallback($callbackPayload);
            }
        } catch (\Exception $e) {
            $message = "Error invoice callback: " . $e->getMessage();
            $this->logger->info($message);

            $result = $this->jsonResultFactory->create();
            /** You may introduce your own constants for this custom REST API */
            $result->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);
            $result->setData([
                'status' => __('ERROR'),
                'message' => $message
            ]);

            return $result;
        }
    }

    /**
     * @param $callbackPayload
     * @return \Magento\Framework\Controller\Result\Json
     * @throws LocalizedException
     */
    public function handleInvoiceCallback($callbackPayload)
    {
        if (!isset($callbackPayload['description']) || !isset($callbackPayload['id'])) {
            $result = $this->jsonResultFactory->create();
            /** You may introduce your own constants for this custom REST API */
            $result->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);
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

        if ($isMultishipping) {
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

    /**
     * @param $callbackPayload
     * @return \Magento\Framework\Controller\Result\Json
     * @throws LocalizedException
     */
    public function handleEwalletCallback($callbackPayload)
    {
        if (!isset($callbackPayload['external_id'])) {
            $result = $this->jsonResultFactory->create();
            /** You may introduce your own constants for this custom REST API */
            $result->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);
            $result->setData([
                'status' => __('ERROR'),
                'message' => 'Callback external_id is invalid'
            ]);

            return $result;
        }

        $orderIdList = explode('-', $callbackPayload['external_id']);
        $orderIds = [];
        foreach ($orderIdList as $orderId) {
            if (is_numeric($orderId)) {
                $orderIds[] = $orderId;
            }
        }
        $this->logger->info("orderIdList");
        $this->logger->info(print_r($orderIdList));
        $this->logger->info(print_r($orderIds));

        $isMultishipping = (count($orderIds) > 1) ? true : false;
        if ($isMultishipping) {
            foreach ($orderIds as $orderId) {
                $order = $this->getOrderById($orderId);
                $result = $this->checkOrder($order, true, $callbackPayload, null, $orderId);
            }
            return $result;
        } else {
            $description = isset($callbackPayload['description']) ? $callbackPayload['description'] : '';
            $order = $this->getOrderById($description);
            return $this->checkOrder($order, true, $callbackPayload, null, $description);
        }
    }

    /**
     * @param $order
     * @param $isEwallet
     * @param $callbackPayload
     * @param $invoice
     * @param $callbackDescription
     * @return \Magento\Framework\Controller\Result\Json
     * @throws LocalizedException
     */
    private function checkOrder($order, $isEwallet, $callbackPayload, $invoice, $callbackDescription)
    {
        $transactionId = '';
        if (isset($callbackPayload['callback_authentication_token'])) {
            $transactionId = $callbackPayload['callback_authentication_token'];
        } elseif (isset($callbackPayload['id'])) {
            $transactionId = $callbackPayload['id'];
        } elseif (isset($callbackPayload['business_id'])) {
            $transactionId = $callbackPayload['business_id'];
        }
        // Kredivo
        elseif (isset($callbackPayload['transaction_id'])) {
            $transactionId = $callbackPayload['transaction_id'];
        }

        if (!$order) {
            $result = $this->jsonResultFactory->create();
            /** You may introduce your own constants for this custom REST API */
            $result->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);
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
        $this->logger->info("checkOrder");
        $paymentStatus = '';
        if (isset($callbackPayload['status'])) {
            $paymentStatus = $callbackPayload['status'];
        } elseif (isset($callbackPayload['payment_status'])) {
            $paymentStatus = $callbackPayload['payment_status'];
        } elseif (isset($callbackPayload['transaction_status'])) {
            $paymentStatus = $callbackPayload['transaction_status'];
        }
        if ($isEwallet) {
            if (isset($callbackPayload['ewallet_type']) && isset($callbackPayload['external_id'])) {
                $paymentStatus = $this->getEwalletStatus($callbackPayload['ewallet_type'], $callbackPayload['external_id']);
            }
        } else {
            $paymentStatus = (isset($invoice['status'])) ? $invoice['status'] : '';

            if (isset($invoice['description'])) {
                if ($invoice['description'] !== $callbackDescription) {
                    $result = $this->jsonResultFactory->create();
                    /** You may introduce your own constants for this custom REST API */
                    $result->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);
                    $result->setData([
                        'status' => __('ERROR'),
                        'message' => 'Invoice is not for this order'
                    ]);

                    return $result;
                }
            }
        }
        $this->logger->info($paymentStatus);
        $statusList = [
            'PAID',
            'SETTLED',
            'COMPLETED',
            'SUCCESS_COMPLETED',
            'settlement'
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

            if ($isEwallet && $transactionId) {
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
                    "Order #" . ($order->getId()) . " was rejected by Xendit. Transaction #$transactionId."
                );
                $this->getCheckoutHelper()->restoreQuote(); //restore cart
            }

            $order->addStatusHistoryComment("Xendit payment " . strtolower($paymentStatus) . ". Transaction ID: $transactionId")
                ->save();

            if ($isEwallet && isset($callbackPayload['failure_code'])) {
                $payment = $order->getPayment();
                $payment->setAdditionalInformation('xendit_ewallet_failure_code', $callbackPayload['failure_code']);
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

    /**
     * @param $invoiceId
     * @return mixed
     * @throws \Magento\Payment\Gateway\Http\ClientException
     */
    private function getXenditInvoice($invoiceId)
    {
        $invoiceUrl = $this->dataHelper->getCheckoutUrl() . "/payment/xendit/invoice/$invoiceId";

        $this->logger->info("getXenditInvoice");
        $this->logger->info($invoiceUrl);
        $invoiceMethod = Request::METHOD_GET;

        try {
            $invoice = $this->apiHelper->request($invoiceUrl, $invoiceMethod);
        } catch (LocalizedException $e) {
            throw new LocalizedException(
                new Phrase($e->getMessage())
            );
        }

        return $invoice;
    }

    /**
     * @param $ewalletType
     * @param $externalId
     * @return string
     * @throws \Magento\Payment\Gateway\Http\ClientException
     */
    private function getEwalletStatus($ewalletType, $externalId)
    {
        $ewalletUrl = $this->dataHelper->getCheckoutUrl() . "/payment/xendit/ewallets?ewallet_type=" . $ewalletType . "&external_id=" . $externalId;
        $ewalletMethod = Request::METHOD_GET;

        try {
            $response = $this->apiHelper->request($ewalletUrl, $ewalletMethod);
        } catch (LocalizedException $e) {
            throw new LocalizedException(
                new Phrase($e->getMessage())
            );
        }

        $statusList = ["COMPLETED", "PAID", "SUCCESS_COMPLETED"]; //OVO, DANA, LINKAJA
        if (in_array($response['status'], $statusList)) {
            return "COMPLETED";
        }

        return $response['status'];
    }

    /**
     * @param $order
     * @param $transactionId
     * @throws LocalizedException
     */
    private function invoiceOrder($order, $transactionId)
    {
        $this->logger->info("invoiceOrder");
        if (!$order->canInvoice()) {
            throw new LocalizedException(
                __('Cannot create an invoice.')
            );
        }

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
     * @return Checkout
     */
    protected function getCheckoutHelper()
    {
        return $this->checkoutHelper;
    }

    /**
     * @param $orderId
     * @return Order|null
     */
    protected function getOrderById($orderId)
    {
        $order = $this->orderFactory->create()->load($orderId);
        if (!$order->getId()) {
            $order = $this->orderFactory->create()->loadByIncrementId($orderId);
            if (!$order->getId()) {
                return null;
            }
        }
        return $order;
    }
}