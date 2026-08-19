<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;

/**
 * Class Invoice
 * @package Xendit\M2Invoice\Controller\Checkout
 */
class Invoice extends AbstractAction
{
    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Redirect|\Magento\Framework\Controller\ResultInterface|void
     * @throws LocalizedException
     */
    public function execute()
    {
        $order = $this->getOrder();
        try {
            if ($order->getState() !== Order::STATE_NEW) {
                if ($order->getState() === Order::STATE_CANCELED) {
                    $this->getLogger()->info('Order is already canceled', ['order_id' => $order->getIncrementId()]);
                    $this->_redirect('checkout/cart');
                    return;
                }
                $this->getLogger()->info('Order in unrecognized state', ['state' => $order->getState(), 'order_id' => $order->getIncrementId()]);
                $this->_redirect('checkout/cart');
                return;
            }

            $orders = [$order];
            $items = $this->buildPaymentSessionItems($orders);

            $payload = $this->buildPaymentSessionPayload(
                $orders,
                (string) $order->getId(),
                sprintf('order entity id: %s, order increment id: %s', $order->getId(), $order->getRealOrderId()),
                'onepage',
                (string) $order->getTotalDue(),
                $order->getBaseCurrencyCode(),
                $items,
                $this->getDataHelper()->getSuccessUrl(),
                $this->getDataHelper()->getFailureUrl([$order->getRealOrderId()])
            );

            $response = $this->sendPaymentSessionRequest($payload);

            $redirectUrl = $response['payment_link_url'];
            $paymentSessionId = $response['payment_session_id'] ?? '';

            $this->applyPaymentSessionToOrders($orders, $paymentSessionId, $redirectUrl);

            $this->getLogger()->info(
                'Redirect customer to Xendit (Payment Session)',
                ['order_id' => $order->getIncrementId(), 'redirect_url' => $redirectUrl]
            );
            $resultRedirect = $this->getRedirectFactory()->create();
            $resultRedirect->setUrl($redirectUrl);
            return $resultRedirect;
        } catch (\Throwable $e) {
            $errorMessage = sprintf(
                'xendit/checkout/invoice failed: Order #%s - %s',
                $order->getIncrementId(),
                $e->getMessage()
            );
            $this->getLogger()->error($errorMessage, ['order_id' => $order->getIncrementId()]);
            $this->getLogger()->debug('Exception caught on xendit/checkout/invoice: ' . $e->getMessage());
            $this->getLogger()->debug($e->getTraceAsString());

            try {
                $this->cancelOrder($order, $e->getMessage());
                $this->metricHelper->sendMetric('magento2_checkout', [
                    'type' => 'error',
                    'error_message' => $errorMessage,
                ]);
            } catch (\Exception $cancelEx) {
                $this->getLogger()->error('Failed to cancel order after Payment Session failure', [
                    'order_id' => $order->getIncrementId(),
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
}
