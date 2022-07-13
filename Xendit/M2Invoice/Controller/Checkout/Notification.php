<?php

namespace Xendit\M2Invoice\Controller\Checkout;

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
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\Service\InvoiceService;
use Xendit\M2Invoice\Helper\ApiRequest;
use Xendit\M2Invoice\Helper\Checkout;
use Xendit\M2Invoice\Helper\Data;
use Xendit\M2Invoice\Logger\Logger as XenditLogger;
use Xendit\M2Invoice\Model\Payment\Xendit;
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
     * @var Xendit
     */
    private $xenditModel;

    private $orderRepository;

    /**
     * @param Context $context
     * @param JsonFactory $jsonResultFactory
     * @param Checkout $checkoutHelper
     * @param OrderFactory $orderFactory
     * @param Data $dataHelper
     * @param ApiRequest $apiHelper
     * @param XenditLogger $logger
     * @param DbTransaction $dbTransaction
     * @param InvoiceService $invoiceService
     * @param Xendit $xenditModel
     */
    public function __construct(
        Context         $context,
        JsonFactory     $jsonResultFactory,
        Checkout        $checkoutHelper,
        OrderFactory    $orderFactory,
        Data            $dataHelper,
        ApiRequest      $apiHelper,
        XenditLogger    $logger,
        DbTransaction   $dbTransaction,
        InvoiceService  $invoiceService,
        Xendit          $xenditModel,
        OrderRepository $orderRepository
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
        $this->xenditModel = $xenditModel;
        $this->orderRepository = $orderRepository;
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
            $callbackPayload = json_decode($post, true);

            $this->logger->info("callbackPayload");
            $this->logger->info($post);

            // Invoice: Regular CC, Ewallet, Retail Outlet, PayLater
            return $this->handleInvoiceCallback($callbackPayload);
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
     * @param string $message
     * @return void
     */
    protected function responseSuccess(string $message)
    {
        $this->logger->info($message);
        $result = $this->jsonResultFactory->create();
        $result->setData([
            'status' => __('SUCCESS'),
            'message' => $message
        ]);
        return $result;
    }

    /**
     * @param string $message
     * @param string $status
     * @return \Magento\Framework\Controller\Result\Json
     */
    protected function responseError(string $message, string $status = 'ERROR')
    {
        $this->logger->error($message);
        $result = $this->jsonResultFactory->create();
        /** You may introduce your own constants for this custom REST API */
        $result->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);
        $result->setData([
            'status' => $status,
            'message' => $message
        ]);

        return $result;
    }

    /**
     * @param $callbackPayload
     * @return \Magento\Framework\Controller\Result\Json|null
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Payment\Gateway\Http\ClientException
     */
    public function handleInvoiceCallback($callbackPayload)
    {
        if (!isset($callbackPayload['description']) || !isset($callbackPayload['id'])) {
            return $this->responseError(__('Callback body is invalid'));
        }

        $description = $callbackPayload['description'];
        $transactionId = $callbackPayload['id'];
        $orderIds = $this->xenditModel->getOrderIdsByTransactionId($transactionId);
        if (empty($orderIds)) {
            return $this->responseError(__('No order(s) found for transaction id %1', $transactionId));
        }

        // Check if Xendit invoice exists on Xendit
        $invoice = $this->getXenditInvoice($transactionId);
        if (empty($invoice)) {
            return $this->responseError(__('The transaction id does not exist'));
        }

        $result = null;
        foreach ($orderIds as $orderId) {
            $order = $this->orderRepository->get($orderId);
            $result = $this->checkOrder($order, $callbackPayload, $invoice, $description);
        }
        return $result;
    }

    /**
     * @param $order
     * @param $callbackPayload
     * @param $invoice
     * @param $callbackDescription
     * @return \Magento\Framework\Controller\Result\Json
     * @throws LocalizedException
     */
    private function checkOrder($order, $callbackPayload, $invoice, $callbackDescription)
    {
        // Check if order exists in Magento
        if (!$order) {
            return $this->responseError(__('Order not found'));
        }

        // Check if order already created invoice
        if (!$order->canInvoice()) {
            return $this->responseSuccess(__('Order is already processed'));
        }

        // Start check order
        $this->logger->info("checkOrder");

        $transactionId = $callbackPayload['id'];
        $paymentStatus = $invoice['status'];

        // Check if the Xendit invoice not belong to the order
        if (isset($invoice['description']) && $invoice['description'] !== $callbackDescription) {
            return $this->responseError(__('Invoice is not for this order'));
        }

        // Start update the order status
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

            if (!empty($invoice['credit_card_charge_id'])) {
                $payment->setAdditionalInformation('xendit_charge_id', $invoice['credit_card_charge_id']);
                $payment->save();
            }

            if ($invoice['payment_channel'] == 'CARD_INSTALLMENT' && !empty($callbackPayload['installment'])) {
                $payment->setAdditionalInformation('xendit_installment', $callbackPayload['installment']);
                $payment->save();
            }
            $order->save();

            // Create invoice for order
            $this->invoiceOrder($order, $transactionId);
            return $this->responseSuccess(__('Transaction paid'));
        } else { //FAILED or EXPIRED
            $orderState = Order::STATE_CANCELED;
            if ($order->getStatus() != $orderState) {
                $this->getCheckoutHelper()->cancelCurrentOrder(
                    "Order #" . ($order->getId()) . " was rejected by Xendit. Transaction #$transactionId."
                );
                $this->getCheckoutHelper()->restoreQuote(); //restore cart
            }
            $order->addStatusHistoryComment(
                "Xendit payment " . strtolower($paymentStatus) . ". Transaction ID: $transactionId"
            )->save();

            return $this->responseError(__('Transaction not paid'), __('FAILED'));
        }
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
        if (!$order->getId() || $orderId !== $order->getId()) {
            $order = $this->orderFactory->create()->loadByIncrementId($orderId);
            if (!$order->getId()) {
                return null;
            }
        }
        return $order;
    }
}
