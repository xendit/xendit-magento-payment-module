<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;

/**
 * Class InvoiceMultishipping
 * @package Xendit\M2Invoice\Controller\Checkout
 */
class InvoiceMultishipping extends AbstractAction
{
    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Redirect|\Magento\Framework\Controller\ResultInterface|null
     * @throws LocalizedException
     */
    public function execute()
    {
        $transactionAmount = 0;
        $orders = [];
        $currency = '';

        $orderIncrementIds = [];
        $orderIds = $this->getMultiShippingOrderIds();
        $rawOrderIds = implode('-', $orderIds);

        try {
            if (empty($orderIds)) {
                $message = __('The order not exist');
                $this->getLogger()->info($message, ['order_ids' => $orderIds]);
                return $this->redirectToCart($message);
            }

            foreach ($orderIds as $orderId) {
                $order = $this->getOrderRepo()->get($orderId);
                if (!$this->orderValidToCreateXenditInvoice($order)) {
                    $message = __('Order processed');
                    $this->getLogger()->info($message, ['order_id' => $orderId]);
                    return $this->redirectToCart($message);
                }

                $orderIncrementIds[] = $order->getRealOrderId();

                $orderState = $order->getState();
                if ($orderState === Order::STATE_PROCESSING && !$order->canInvoice()) {
                    continue;
                }

                $orders[] = $order;

                $transactionAmount += $order->getTotalDue();
                $currency = $order->getBaseCurrencyCode();
            }

            $items = $this->buildPaymentSessionItems($orders);

            $payload = $this->buildPaymentSessionPayload(
                $orders,
                $rawOrderIds,
                sprintf('order entity id: %s, order increment id: %s', $rawOrderIds, implode('-', $orderIncrementIds)),
                'multishipping',
                (string) $transactionAmount,
                $currency,
                $items,
                $this->getDataHelper()->getSuccessUrl(true),
                $this->getDataHelper()->getFailureUrl($orderIncrementIds)
            );

            $response = $this->sendPaymentSessionRequest($payload);

            $redirectUrl = $response['payment_link_url'];
            $paymentSessionId = $response['payment_session_id'] ?? '';

            $this->applyPaymentSessionToOrders($orders, $paymentSessionId, $redirectUrl);

            $this->getLogger()->info(
                'Redirect customer to Xendit (Payment Session multishipping)',
                array_merge($this->getLogContext($orders), ['redirect_url' => $redirectUrl])
            );

            $resultRedirect = $this->getRedirectFactory()->create();
            $resultRedirect->setUrl($redirectUrl);
            return $resultRedirect;
        } catch (\Throwable $e) {
            $logContext = $this->getLogContext($orders);
            $message = sprintf(
                'Exception caught on xendit/checkout/invoicemultishipping: Order_ids %s - %s',
                implode(', ', $logContext['order_ids'] ?? []),
                $e->getMessage()
            );
            $this->getLogger()->error($message, $logContext);

            try {
                $this->processFailedPayment($orderIds, $message);
                $this->metricHelper->sendMetric('magento2_checkout', [
                    'type' => 'error',
                    'error_message' => $message,
                ]);
            } catch (\Exception $cancelEx) {
                $this->getLogger()->error('Failed to cancel orders after Payment Session failure', [
                    'order_ids' => $orderIds,
                    'cancel_error' => $cancelEx->getMessage(),
                ]);
            }

            return $this->redirectToCart($e->getMessage());
        }
    }

    /**
     * @param $failureReason
     * @return \Magento\Framework\Controller\ResultInterface
     */
    private function redirectToCart($failureReason)
    {
        $failureReasonInsight = $this->getDataHelper()->failureReasonInsight($failureReason);
        $this->getMessageManager()->addErrorMessage(__(
            $failureReasonInsight
        ));
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($this->_url->getUrl('checkout/cart'), ['_secure' => false]);
        return $resultRedirect;
    }

    /**
     * @param array $orderIds
     * @param string $failureReason
     * @return void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function processFailedPayment(array $orderIds, string $failureReason = 'Unexpected Error with empty charge')
    {
        $this->getCheckoutHelper()->processOrdersFailedPayment($orderIds, $failureReason);

        $failureReasonInsight = $this->getDataHelper()->failureReasonInsight($failureReason);
        $this->getMessageManager()->addErrorMessage(__(
            $failureReasonInsight
        ));
        $this->_redirect('checkout/cart', ['_secure' => false]);
    }

    /**
     * @param array $orders
     * @param array $invoice
     * @return array
     */
    protected function getLogContext(array $orders, array $invoice = []): array
    {
        $context['order_ids'] = array_map(function (Order $order) {
            return $order->getIncrementId();
        }, $orders);
        if (!empty($invoice) && !empty($invoice['id'])) {
            $context['xendit_transaction_id'] = $invoice['id'];
        }
        return $context;
    }
}
