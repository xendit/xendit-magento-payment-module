<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Sales\Model\Order;

/**
 * Class Failure
 * @package Xendit\M2Invoice\Controller\Checkout
 */
class Failure extends AbstractAction
{
    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute()
    {
        $orderCanceled = false;
        $orderIds = $this->getRequest()->get('order_ids') ?? [];
        if (!is_array($orderIds)) {
            $this->getMessageManager()->addErrorMessage(__('Invalid parameters'));
            return $this->_redirect('checkout/cart');
        }

        $restoreQuote = null;
        try {
            foreach ($orderIds as $orderId) {
                $order = $this->getOrderByIncrementId($orderId);
                if ($order->getState() == Order::STATE_CANCELED) {
                    $orderCanceled = true;
                    $restoreQuote = $this->getQuoteRepository()->get($order->getQuoteId());
                }
            }
        } catch (\Exception $e) {
            $this->getMessageManager()->addErrorMessage($e->getMessage());
            return $this->_redirect('checkout/cart');
        }

        // Add error message if order canceled
        if ($orderCanceled && !empty($restoreQuote)) {
            $this->getCheckoutHelper()->restoreQuote($restoreQuote);
            $this->getMessageManager()->addWarningMessage(__("Xendit payment failed. Please click on 'Update Shopping Cart'."));
        }
        return $this->_redirect('checkout/cart');
    }
}
