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
use Magento\Framework\Webapi\Exception;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Xendit\M2Invoice\Helper\Checkout;
use Xendit\M2Invoice\Helper\IntegrationNotificationKeys;
use Xendit\M2Invoice\Helper\SignatureVerifier;
use Xendit\M2Invoice\Logger\Logger as XenditLogger;

/**
 * Integration Notification controller for Payment Session flow.
 *
 * Receives signed notifications from TPI Service when a payment session
 * status changes (COMPLETED, CANCELED, EXPIRED). This is a NEW controller —
 * separate from the legacy Notification.php which handles Invoice API callbacks.
 *
 * Route: POST xendit/checkout/integrationNotification
 *
 * Kept separate from legacy Notification.php to avoid coupling with the Invoice API flow.
 */
class IntegrationNotification extends Action implements CsrfAwareActionInterface
{
    /**
     * @var JsonFactory
     */
    private $jsonResultFactory;

    /**
     * @var OrderRepository
     */
    private $orderRepository;

    /**
     * @var InvoiceService
     */
    private $invoiceService;

    /**
     * @var DbTransaction
     */
    private $dbTransaction;

    /**
     * @var Checkout
     */
    private $checkoutHelper;

    /**
     * @var XenditLogger
     */
    private $logger;

    /**
     * @var SignatureVerifier
     */
    private $signatureVerifier;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param Context $context
     * @param JsonFactory $jsonResultFactory
     * @param OrderRepository $orderRepository
     * @param InvoiceService $invoiceService
     * @param DbTransaction $dbTransaction
     * @param Checkout $checkoutHelper
     * @param XenditLogger $logger
     * @param SignatureVerifier $signatureVerifier
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Context $context,
        JsonFactory $jsonResultFactory,
        OrderRepository $orderRepository,
        InvoiceService $invoiceService,
        DbTransaction $dbTransaction,
        Checkout $checkoutHelper,
        XenditLogger $logger,
        SignatureVerifier $signatureVerifier,
        ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($context);
        $this->jsonResultFactory = $jsonResultFactory;
        $this->orderRepository = $orderRepository;
        $this->invoiceService = $invoiceService;
        $this->dbTransaction = $dbTransaction;
        $this->checkoutHelper = $checkoutHelper;
        $this->logger = $logger;
        $this->signatureVerifier = $signatureVerifier;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @inheritdoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $post = $this->getRequest()->getContent();
        $payload = json_decode($post, true);

        $this->logger->info('[IntegrationNotification] Received', [
            '_id' => $payload['_id'] ?? 'unknown',
            'status' => $payload['status'] ?? 'unknown',
            'integration_name' => $payload['integration_name'] ?? 'unknown',
        ]);

        try {
            return $this->handleNotification($payload);
        } catch (\Throwable $e) {
            $message = '[IntegrationNotification] Error: ' . $e->getMessage();
            $this->logger->error($message, ['payload_id' => $payload['_id'] ?? 'unknown']);

            $result = $this->jsonResultFactory->create();
            $result->setHttpResponseCode(Exception::HTTP_INTERNAL_ERROR);
            $result->setData([
                'status' => 'ERROR',
                'message' => $message,
            ]);
            return $result;
        }
    }

    /**
     * @param array $payload
     * @return \Magento\Framework\Controller\Result\Json
     */
    private function handleNotification(array $payload)
    {
        // Validate required fields
        if (empty($payload['_id']) || empty($payload['signature'])) {
            return $this->responseError('Missing required fields (_id, signature)', 400);
        }

        // Verify ECDSA signature
        if (!$this->verifyPayloadSignature($payload)) {
            $this->logger->error('[IntegrationNotification] Signature verification failed', [
                '_id' => $payload['_id'],
            ]);
            return $this->responseError('Invalid signature', 400);
        }

        // Extract Magento-specific fields
        $idempotencyKey = $payload['magento_checkout_idempotency_key'] ?? '';
        $checkoutType = $payload['magento_checkout_type'] ?? null;

        if (empty($idempotencyKey)) {
            return $this->responseError('Missing magento_checkout_idempotency_key', 400);
        }

        if (empty($checkoutType) || !in_array($checkoutType, ['onepage', 'multishipping'], true)) {
            return $this->responseError('Missing or invalid magento_checkout_type', 400);
        }

        $session = $payload['session'] ?? null;
        if (empty($session)) {
            return $this->responseError('Missing session data', 400);
        }

        $sessionStatus = $session['status'] ?? '';
        $paymentSessionId = $session['payment_session_id'] ?? '';
        $paymentId = $session['payment_id'] ?? '';

        $this->logger->info('[IntegrationNotification] Processing', [
            '_id' => $payload['_id'],
            'session_status' => $sessionStatus,
            'checkout_type' => $checkoutType,
            'idempotency_key' => $idempotencyKey,
            'payment_session_id' => $paymentSessionId,
        ]);

        // The idempotency key carries entity IDs: single order = "42", multishipping = "42-43-44".
        // Split on '-' to handle both cases uniformly — multishipping groups multiple orders
        // into a single payment session with a composite idempotency key.
        $orderEntityIds = array_map('trim', explode('-', $idempotencyKey));

        switch ($sessionStatus) {
            case 'COMPLETED':
                return $this->handleCompleted($orderEntityIds, $paymentId, $paymentSessionId);
            case 'CANCELED':
            case 'EXPIRED':
                return $this->handleCanceledOrExpired($orderEntityIds, $sessionStatus, $paymentSessionId);
            default:
                $this->logger->info('[IntegrationNotification] Unhandled session status', [
                    'status' => $sessionStatus,
                ]);
                return $this->responseSuccess('Acknowledged');
        }
    }

    /**
     * Verify the ECDSA signature on the integration notification payload.
     *
     * Reconstructs the signed message using the same algorithm as TPI Service's genMagentoMessage:
     * {_id}.{status}.{session_id}.{session_status}.{token_id}.{token_status}.{pr_id}.{pr_status}.{refund_id}.{refund_status}
     * ?magento_checkout_idempotency_key={key}&magento_checkout_type={type}
     *
     * @param array $payload
     * @return bool
     */
    private function verifyPayloadSignature(array $payload): bool
    {
        $session = $payload['session'] ?? [];
        $paymentToken = $payload['payment_token'] ?? [];
        $paymentRequest = $payload['payment_request'] ?? [];
        $refund = $payload['refund'] ?? [];

        $parts = [
            $payload['_id'] ?? '',
            $payload['status'] ?? '',
            $session['payment_session_id'] ?? '',
            $session['status'] ?? '',
            $paymentToken['payment_token_id'] ?? '',
            $paymentToken['status'] ?? '',
            $paymentRequest['payment_request_id'] ?? '',
            $paymentRequest['status'] ?? '',
            $refund['refund_id'] ?? '',
            $refund['status'] ?? '',
        ];

        $message = implode('.', $parts)
            . '?magento_checkout_idempotency_key=' . ($payload['magento_checkout_idempotency_key'] ?? '')
            . '&magento_checkout_type=' . ($payload['magento_checkout_type'] ?? '');

        $signature = $payload['signature'] ?? '';
        $appEnv = $this->scopeConfig->getValue(
            'payment/xendit/xendit_app_env',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        ) ?: 'production';

        $publicKeys = IntegrationNotificationKeys::getPublicKeys($appEnv);

        return $this->signatureVerifier->verifyWithMultipleKeys($message, $signature, $publicKeys);
    }

    /**
     * Handle COMPLETED payment session: invoice order(s) and store transaction IDs.
     *
     * @param array $orderEntityIds
     * @param string $paymentId Payments API v3 payment_id
     * @param string $paymentSessionId
     * @return \Magento\Framework\Controller\Result\Json
     */
    private function handleCompleted(array $orderEntityIds, string $paymentId, string $paymentSessionId)
    {
        foreach ($orderEntityIds as $entityId) {
            try {
                /** @var Order $order */
                $order = $this->orderRepository->get($entityId);

                // If order was canceled (e.g., race condition where cancel notification
                // arrived before completion), revert to pending so it can be invoiced.
                if ($this->checkoutHelper->canRevertOrderStatusToPending($order)) {
                    $this->checkoutHelper->revertCancelledOrderToPending($order);
                }

                // Skip if already invoiced
                if (!$order->canInvoice()) {
                    $this->logger->info('[IntegrationNotification] Order already invoiced', [
                        'order_id' => $order->getIncrementId(),
                    ]);
                    continue;
                }

                // Set order to PROCESSING
                $order->setState(Order::STATE_PROCESSING)
                    ->setStatus(Order::STATE_PROCESSING)
                    ->addCommentToStatusHistory(
                        "Xendit payment completed. Payment Session: $paymentSessionId"
                    );

                // Store transaction IDs: payment_id for Magento refunds, payment_session_id for TPI lookup
                $payment = $order->getPayment();

                // payment_id from Payments API v3 — used by Magento's refund mechanism
                // and stored in xendit_transaction_id for TPI Service lookup
                if (!empty($paymentId)) {
                    $payment->setTransactionId($paymentId);
                    $payment->addTransaction(TransactionInterface::TYPE_CAPTURE, null, true);
                }

                // payment_id stored in custom sales_order column — used by TPI Service
                // for order lookup via getOrderIdsByTransactionId()
                $order->setXenditTransactionId(!empty($paymentId) ? $paymentId : $paymentSessionId);

                $this->orderRepository->save($order);

                // Create Magento invoice
                $this->invoiceOrder($order, $paymentId ?: $paymentSessionId);

                $this->logger->info('[IntegrationNotification] Order invoiced', [
                    'order_id' => $order->getIncrementId(),
                    'payment_id' => $paymentId,
                    'payment_session_id' => $paymentSessionId,
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('[IntegrationNotification] Failed to invoice order', [
                    'entity_id' => $entityId,
                    'error' => $e->getMessage(),
                ]);
                return $this->responseError('Failed to process order ' . $entityId, 500);
            }
        }

        return $this->responseSuccess('Payment completed');
    }

    /**
     * Handle CANCELED or EXPIRED payment session: cancel order(s).
     *
     * @param array $orderEntityIds
     * @param string $status 'CANCELED' or 'EXPIRED'
     * @param string $paymentSessionId
     * @return \Magento\Framework\Controller\Result\Json
     */
    private function handleCanceledOrExpired(array $orderEntityIds, string $status, string $paymentSessionId)
    {
        $reason = "Xendit payment " . strtolower($status) . ". Payment Session: $paymentSessionId";

        foreach ($orderEntityIds as $entityId) {
            try {
                /** @var Order $order */
                $order = $this->orderRepository->get($entityId);

                if ($order->getStatus() === Order::STATE_CANCELED) {
                    $this->logger->info('[IntegrationNotification] Order already canceled', [
                        'order_id' => $order->getIncrementId(),
                    ]);
                    continue;
                }

                // Skip orders that are already paid/processing
                if ($order->getState() === Order::STATE_PROCESSING) {
                    $this->logger->info('[IntegrationNotification] Order already processing, skipping cancel', [
                        'order_id' => $order->getIncrementId(),
                    ]);
                    continue;
                }

                $this->checkoutHelper->cancelOrder($order, $reason);
                $order->addCommentToStatusHistory($reason);
                $this->orderRepository->save($order);

                $this->logger->info('[IntegrationNotification] Order canceled', [
                    'order_id' => $order->getIncrementId(),
                    'status' => $status,
                    'payment_session_id' => $paymentSessionId,
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('[IntegrationNotification] Failed to cancel order', [
                    'entity_id' => $entityId,
                    'error' => $e->getMessage(),
                ]);
                // Continue canceling remaining orders
            }
        }

        return $this->responseSuccess('Payment ' . strtolower($status));
    }

    /**
     * Create a Magento invoice for the order.
     *
     * @param Order $order
     * @param string $transactionId
     * @throws LocalizedException
     */
    private function invoiceOrder(Order $order, string $transactionId): void
    {
        if (!$order->canInvoice()) {
            throw new LocalizedException(__('Cannot create an invoice.'));
        }

        $invoice = $this->invoiceService->prepareInvoice($order);
        if (!$invoice->getTotalQty()) {
            throw new LocalizedException(__('You can\'t create an invoice without products.'));
        }

        if (!empty($transactionId)) {
            $invoice->setTransactionId($transactionId);
        }
        $invoice->setRequestedCaptureCase(Order\Invoice::CAPTURE_OFFLINE);
        $invoice->register();

        $transaction = $this->dbTransaction->addObject($invoice)->addObject($invoice->getOrder());
        $transaction->save();
    }

    /**
     * @param string $message
     * @return \Magento\Framework\Controller\Result\Json
     */
    private function responseSuccess(string $message)
    {
        $result = $this->jsonResultFactory->create();
        $result->setData([
            'status' => 'SUCCESS',
            'message' => $message,
        ]);
        return $result;
    }

    /**
     * @param string $message
     * @param int $httpCode
     * @return \Magento\Framework\Controller\Result\Json
     */
    private function responseError(string $message, int $httpCode = 400)
    {
        $result = $this->jsonResultFactory->create();
        $result->setHttpResponseCode($httpCode);
        $result->setData([
            'status' => 'ERROR',
            'message' => $message,
        ]);
        return $result;
    }
}
