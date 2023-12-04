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
use Magento\Sales\Api\Data\TransactionInterface;
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

    /**
     * @var OrderRepository
     */
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
     * @param OrderRepository $orderRepository
     */
    public function __construct(
        Context             $context,
        JsonFactory         $jsonResultFactory,
        Checkout            $checkoutHelper,
        OrderFactory        $orderFactory,
        Data                $dataHelper,
        ApiRequest          $apiHelper,
        XenditLogger        $logger,
        DbTransaction       $dbTransaction,
        InvoiceService      $invoiceService,
        Xendit              $xenditModel,
        OrderRepository     $orderRepository
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
        $post = $this->getRequest()->getContent();
        $callbackPayload = json_decode($post, true);
        $this->logger->info("callbackPayload", $callbackPayload);

        try {
            // Invoice: Regular CC, Ewallet, Retail Outlet, PayLater
            return $this->handleInvoiceCallback($callbackPayload);
        } catch (\Exception $e) {
            $message = "Error invoice callback: " . $e->getMessage();
            $this->logger->error($message, $callbackPayload);

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
     * @param string $status
     * @return bool
     */
    protected function isXenditInvoicePaid(string $status): bool
    {
        return in_array($status, ['PAID', 'SETTLED']);
    }

    /**
     * @param string $message
     * @param array $context
     * @return \Magento\Framework\Controller\Result\Json
     */
    protected function responseSuccess(string $message, array $context = [])
    {
        $this->logger->info($message, $context);
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
     * @param array $context
     * @return \Magento\Framework\Controller\Result\Json
     */
    protected function responseError(string $message, string $status = 'ERROR', array $context = [])
    {
        $this->logger->error($message, $context);
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
     * @param string $incrementId
     * @return int|null
     * @throws \Exception
     */
    protected function getOrderByIncrementId(string $incrementId)
    {
        try {
            $order = $this->xenditModel->getOrderByIncrementId($incrementId);
            $this->logger->info('getOrderByIncrementId', ['increment_id' => $incrementId, 'order_id' => $order->getEntityId()]);

            return $order->getEntityId();
        } catch (\Exception $ex) {
            $this->logger->error($ex->getMessage(), ['order_id' => $incrementId]);
            throw new \Exception(__('#%1: %2', $incrementId, $ex->getMessage()));
        }
    }

    /**
     * @param string $successUrl
     * @return bool
     */
    protected function isMultiShippingOrder(string $successUrl): bool
    {
        $parseUrl = parse_url($successUrl);
        if (empty($parseUrl['query'])) {
            return false;
        }
        parse_str($parseUrl['query'], $params);
        return !empty($params['type']) && $params['type'] === 'multishipping';
    }

    /**
     * @param array $invoice
     * @return array
     * @throws \Exception
     */
    protected function extractOrderIdsFromXenditInvoice(array $invoice): array
    {
        $orderIds = $this->xenditModel->getOrderIdsByTransactionId($invoice['id']);
        if (empty($orderIds)) {
            // Give the second chance to get order from callback description (order_id)
            if (!empty($invoice['success_redirect_url']) &&
                $this->isMultiShippingOrder($invoice['success_redirect_url'])) {
                $orderIds = array_map('trim', explode('-', $invoice['description']));

                $this->logger->info('multiShippingOrder', ['order_ids' => $orderIds]);
            } else {
                $orderIds[] = $this->getOrderByIncrementId($invoice['description']);
            }
        }
        return $orderIds;
    }

    /**
     * @param $callbackPayload
     * @return \Magento\Framework\Controller\Result\Json|null
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Payment\Gateway\Http\ClientException
     * @throws \Exception
     */
    public function handleInvoiceCallback($callbackPayload)
    {
        if (!isset($callbackPayload['description']) || !isset($callbackPayload['id'])) {
            return $this->responseError(__('Callback body is invalid'));
        }

        $description = $callbackPayload['description'];
        $transactionId = $callbackPayload['id'];

        // Check if Xendit invoice exists on Xendit
        $invoice = $this->getXenditInvoice($transactionId);
        if (empty($invoice)) {
            return $this->responseError(__('The transaction id does not exist'));
        }

        $orderIds = $this->extractOrderIdsFromXenditInvoice($invoice);
        if (empty($orderIds)) {
            return $this->responseError(__('No order(s) found for transaction id %1', $transactionId));
        }

        $result = null;
        foreach ($orderIds as $orderId) {
            $order = $this->orderRepository->get($orderId);
            $result = $this->checkOrder($order, $callbackPayload, $invoice, $description);
        }
        return $result;
    }

    /**
     * @param Order $order
     * @param $callbackPayload
     * @param $invoice
     * @param $callbackDescription
     * @return \Magento\Framework\Controller\Result\Json|null
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function checkOrder(Order $order, $callbackPayload, $invoice, $callbackDescription)
    {
        // Start check order
        $transactionId = $callbackPayload['id'];
        $paymentStatus = $invoice['status'];
        $this->logger->info(
            "checkOrder",
            ['order_id' => $order->getIncrementId(), 'xendit_transaction_id' => $transactionId, 'status' => $paymentStatus]
        );

        // Check if order is canceled
        try {
            if ($this->checkoutHelper->canRevertOrderStatusToPending($order)
                && $this->isXenditInvoicePaid($paymentStatus)) {
                $this->checkoutHelper->revertCancelledOrderToPending($order);
            }
        } catch (\Exception $e) {
            return $this->responseError($e->getMessage(), __('FAILED'));
        }

        // Check if order already created invoice
        if (!$order->canInvoice()) {
            return $this->responseSuccess(
                __('Order is already processed'),
                ['order_id' => $order->getIncrementId()]
            );
        }

        // Check if the Xendit invoice not belong to the order
        if (isset($invoice['description']) && $invoice['description'] !== $callbackDescription) {
            return $this->responseError(
                __('Invoice is not for this order'),
                __('ERROR'),
                ['callback_description' => $callbackDescription, 'invoice_description' => $invoice['description']]
            );
        }

        // Start update the order status
        $this->logger->info($paymentStatus);
        if ($this->isXenditInvoicePaid($paymentStatus)) {
            // Change order status
            $order->setState(Order::STATE_PROCESSING)
                ->setStatus(Order::STATE_PROCESSING)
                ->addCommentToStatusHistory("Xendit payment completed. Transaction ID: $transactionId");

            $payment = $order->getPayment();
            $payment->setTransactionId($transactionId);
            $payment->addTransaction(TransactionInterface::TYPE_CAPTURE, null, true);

            if (!empty($invoice['credit_card_charge_id'])) {
                $payment->setAdditionalInformation('xendit_charge_id', $invoice['credit_card_charge_id']);
                $payment->save();
            }

            if ($invoice['payment_channel'] == 'CARD_INSTALLMENT' && !empty($callbackPayload['installment'])) {
                $payment->setAdditionalInformation('xendit_installment', $callbackPayload['installment']);
                $payment->save();
            }

            // insert xendit_transaction_id if order missing this
            if (empty($order->getXenditTransactionId())) {
                $order->setXenditTransactionId($transactionId);
            }

            $this->orderRepository->save($order);

            // Create invoice for order
            $this->invoiceOrder($order, $transactionId);
            return $this->responseSuccess(
                __('Transaction paid'),
                ['order_id' => $order->getIncrementId(), 'xendit_transaction_id' => $transactionId]
            );
        } else { //FAILED or EXPIRED
            if ($order->getStatus() != Order::STATE_CANCELED) {
                $this->getCheckoutHelper()
                    ->cancelOrder($order, __("Order #%1 was rejected by Xendit. Transaction #%2.", $order->getId(), $transactionId));
                $this->getCheckoutHelper()->restoreQuote(); //restore cart
            }

            $order->addCommentToStatusHistory(
                "Xendit payment " . strtolower($paymentStatus) . ". Transaction ID: $transactionId"
            );
            $this->orderRepository->save($order);
            return $this->responseError(
                __('Transaction not paid'),
                __('FAILED'),
                ['order_id' => $order->getIncrementId(), 'xendit_transaction_id' => $transactionId]
            );
        }
    }

    /**
     * @param $invoiceId
     * @return mixed
     * @throws \Magento\Payment\Gateway\Http\ClientException
     */
    private function getXenditInvoice($invoiceId)
    {
        $invoiceUrl = $this->dataHelper->getXenditApiUrl() . "/tpi/payment/xendit/invoice/$invoiceId";

        $this->logger->info("getXenditInvoice", ['get_invoice_url' => $invoiceUrl]);
        $invoiceMethod = Request::METHOD_GET;

        try {
            $invoice = $this->apiHelper->request($invoiceUrl, $invoiceMethod);

            if (isset($invoice['error_code'])) {
                throw new LocalizedException(new Phrase($invoice['message']));
            }
        } catch (LocalizedException $e) {
            throw new LocalizedException(
                new Phrase($e->getMessage())
            );
        }

        return $invoice;
    }

    /**
     * @param Order $order
     * @param string $transactionId
     * @return void
     * @throws LocalizedException
     */
    private function invoiceOrder(Order $order, string $transactionId)
    {
        $this->logger->info("invoiceOrder", ['order_id' => $order->getIncrementId(), 'xendit_transaction_id' => $transactionId]);
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
}
