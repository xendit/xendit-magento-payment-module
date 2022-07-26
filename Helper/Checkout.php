<?php

namespace Xendit\M2Invoice\Helper;

use Magento\Checkout\Model\Session;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;

/**
 * Class Checkout
 * @package Xendit\M2Invoice\Helper
 */
class Checkout
{
    /**
     * @var Session
     */
    private $session;

    /**
     * @var OrderFactory
     */
    private $orderFactory;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * Checkout constructor.
     * @param Session $session
     * @param OrderFactory $order
     * @param CartRepositoryInterface $quoteRepository
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        Session $session,
        OrderFactory $order,
        CartRepositoryInterface $quoteRepository,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->session = $session;
        $this->orderFactory = $order;
        $this->quoteRepository = $quoteRepository;
        $this->orderRepository = $orderRepository;
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
            $order->registerCancellation($comment);
            return $this->orderRepository->save($order);
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
            $order->registerCancellation($comment);
            return $this->orderRepository->save($order);
        }
        return false;
    }

    /**
     * @param Order $order
     * @param string $comment
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function cancelOrder(Order $order, string $comment = "")
    {
        if ($order->getState() != Order::STATE_CANCELED) {
            $order->registerCancellation($comment);
            return $this->orderRepository->save($order);
        }
        return false;
    }

    /**
     * Cancel multiple orders
     *
     * @param array $orderIds Prefixless order IDs
     * @param string $failureReason
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function processOrdersFailedPayment($orderIds, $failureReason = 'Unexpected Error with empty charge')
    {
        foreach ($orderIds as $key => $value) {
            $order = $this->orderRepository->get($value);
            $orderState = Order::STATE_CANCELED;
            $order->setState($orderState)
                    ->setStatus($orderState)
                    ->addStatusHistoryComment("Order #" . $value . " was rejected by Xendit because " . $failureReason);

            $order = $this->orderRepository->save($order);
            $quoteId = $order->getQuoteId();
            $quote = $this->quoteRepository->get($quoteId);
            $this->restoreQuote($quote);
        }
    }
}
