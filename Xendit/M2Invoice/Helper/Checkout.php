<?php

namespace Xendit\M2Invoice\Helper;

use Magento\Sales\Model\Order;
use Magento\Checkout\Model\Session;
use Magento\Sales\Model\OrderFactory;

class Checkout
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $session;
    /**
     * @param \Magento\Checkout\Model\Session $session
     */

    protected $orderFactory;

    public function __construct(
        Session $session,
        OrderFactory $order
    ) {
        $this->session = $session;
        $this->orderFactory = $order;
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
    public function restoreQuote()
    {
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
}