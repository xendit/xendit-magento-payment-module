<?php

namespace Xendit\M2Invoice\Helper;

use Magento\Checkout\Model\Session;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;

class Checkout
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $session;
    /**
     * @param \Magento\Checkout\Model\Session $session
     */

    private $orderFactory;

    /**
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     */
    private $quoteRepository;

    public function __construct(
        Session $session,
        OrderFactory $order,
        CartRepositoryInterface $quoteRepository
    ) {
        $this->session = $session;
        $this->orderFactory = $order;
        $this->quoteRepository = $quoteRepository;
    }
    /**
     * Cancel last placed order with specified comment message
     *
     * @param string $comment Comment appended to order history
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return bool True if order cancelled, false otherwise
     */
    public function cancelCurrentOrder($comment)
    {
        $order = $this->session->getLastRealOrder();

        if ($order->getId() && $order->getState() != Order::STATE_CANCELED) {
            $order->registerCancellation($comment)->save();
            return true;
        }
        return false;
    }
    /**
     * Restores quote (restores cart)
     *
     * @return bool
     */
    public function restoreQuote($quote = null)
    {
        if ($quote) {
            $quote->setIsActive(true)->save();
        }

        return $this->session->restoreQuote();
    }
    /**
     * Cancel specified order with specified comment message
     *
     * @param string $comment Comment appended to order history
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return bool True if order cancelled, false otherwise
     */
    public function cancelOrderById($orderId, $comment)
    {
        $order = $this->orderFactory->create()->loadByIncrementId($orderId);

        if ($order->getId() && $order->getState() != Order::STATE_CANCELED) {
            $order->registerCancellation($comment)->save();
            return true;
        }
        return false;
    }

    /**
     * Cancel multiple orders
     * 
     * @param array $orderIds Prefixless order IDs
     * @param string $failureReason
     */
    public function processOrdersFailedPayment($orderIds, $failureReason = 'Unexpected Error with empty charge')
    {
        foreach ($orderIds as $key => $value) {
            $order = $this->orderFactory->create();
            $order  ->load($value);

            $orderState = Order::STATE_CANCELED;
            $order  ->setState($orderState)
                    ->setStatus($orderState)
                    ->addStatusHistoryComment("Order #" . $value . " was rejected by Xendit because " . $failureReason);
            $order  ->save();

            $quoteId = $order->getQuoteId();
            $quote = $this->quoteRepository->get($quoteId);
            $this->restoreQuote($quote);
        }
    }
}
