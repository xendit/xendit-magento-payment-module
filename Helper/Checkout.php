<?php

namespace Xendit\M2Invoice\Helper;

use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\CatalogInventory\Api\StockManagementInterface;
use Magento\CatalogInventory\Model\Indexer\Stock\Processor as StockProcessor;
use Magento\CatalogInventory\Model\Stock\StockItemRepository;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\ItemRepository as OrderItemRepository;
use Magento\Sales\Model\OrderFactory;

/**
 * Class Checkout
 * @package Xendit\M2Invoice\Helper
 */
class Checkout
{
    const ORDER_STATUS_INSUFFICIENT_INVENTORY = 'insufficient_inventory';

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
     * @var OrderItemRepository
     */
    private $orderItemRepository;

    /**
     * @var StockManagementInterface
     */
    private $stockManagement;

    /**
     * @var StockItemRepository
     */
    private $stockItemRepository;

    /**
     * @var StockProcessor
     */
    private $stockIndexerProcessor;

    /**
     * Checkout constructor.
     *
     * @param Session $session
     * @param OrderFactory $order
     * @param CartRepositoryInterface $quoteRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderItemRepository $orderItemRepository
     * @param StockManagementInterface $stockManagement
     * @param StockProcessor $stockIndexerProcessor
     * @param StockItemRepository $stockItemRepository
     */
    public function __construct(
        Session $session,
        OrderFactory $order,
        CartRepositoryInterface $quoteRepository,
        OrderRepositoryInterface $orderRepository,
        OrderItemRepository $orderItemRepository,
        StockManagementInterface $stockManagement,
        StockProcessor $stockIndexerProcessor,
        StockItemRepository $stockItemRepository
    ) {
        $this->session = $session;
        $this->orderFactory = $order;
        $this->quoteRepository = $quoteRepository;
        $this->orderRepository = $orderRepository;
        $this->orderItemRepository = $orderItemRepository;
        $this->stockManagement = $stockManagement;
        $this->stockIndexerProcessor = $stockIndexerProcessor;
        $this->stockItemRepository = $stockItemRepository;
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
    public function processOrdersFailedPayment(array $orderIds, string $failureReason = 'Unexpected Error with empty charge')
    {
        foreach ($orderIds as $key => $value) {
            $order = $this->orderRepository->get($value);
            $orderState = Order::STATE_CANCELED;
            $order->setState($orderState)
                    ->setStatus($orderState)
                    ->addCommentToStatusHistory("Order #" . $value . " was rejected by Xendit because " . $failureReason);

            $order = $this->orderRepository->save($order);
            $quoteId = $order->getQuoteId();
            $quote = $this->quoteRepository->get($quoteId);
            $this->restoreQuote($quote);
        }
    }

    /**
     * Reduce stock for sales and reindex stock
     *
     * @param array $productStockQty
     * @param Order $order
     * @return void
     * @throws LocalizedException
     * @throws \Magento\CatalogInventory\Model\StockStateException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    protected function reduceProductSockForSales(array $productStockQty, Order $order)
    {
        try {
            $itemsForReindex = $this->stockManagement->registerProductsSale(
                $productStockQty,
                $order->getStore()->getWebsiteId()
            );
            $productIds = [];
            foreach ($itemsForReindex as $item) {
                $this->stockItemRepository->save($item);
                $productIds[] = $item->getProductId();
            }
            if (!empty($productIds)) {
                $this->stockIndexerProcessor->reindexList($productIds);
            }
        } catch (Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __($e->getMessage())
            );
        }
    }

    /**
     * @param Order $order
     * @param string $orderState
     * @return Order
     */
    protected function revertOrderStatusToPending(Order $order, string $orderState): Order
    {
        $currentOrderState = $order->getState();
        $order->setBaseDiscountCanceled(0)
            ->setBaseShippingCanceled(0)
            ->setBaseSubtotalCanceled(0)
            ->setBaseTaxCanceled(0)
            ->setBaseTotalCanceled(0)
            ->setDiscountCanceled(0)
            ->setShippingCanceled(0)
            ->setSubtotalCanceled(0)
            ->setTaxCanceled(0)
            ->setTotalCanceled(0);

        // Update status to PENDING_PAYMENT
        $order->setStatus($orderState)
            ->setState($orderState);
        $order->addCommentToStatusHistory(__('Revert status %1 to pending_payment', $currentOrderState));

        return $order;
    }

    /**
     * @param Order $order
     * @return Order
     */
    protected function setOrderItemsToCanceled(Order $order): Order
    {
        foreach ($order->getAllItems() as $item) {
            if ($item->getQtyCanceled() == 0) {
                $item->cancel();
            }
        }
        return $order;
    }

    /**
     * @param Order $order
     * @return array
     * @throws LocalizedException
     */
    protected function getOrderItemsQty(Order $order): array
    {
        $productStockQty = [];
        foreach ($order->getAllVisibleItems() as $orderItem) {
            /** @var ProductInterface $orderItemProduct */
            $orderItemProduct = $orderItem->getProduct();
            $productStockQty[$orderItemProduct->getId()] = $orderItem->getQtyCanceled();
            if (!$orderItemProduct->isSalable()) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __(
                        'Payment processed on Xendit (transaction_id %1). Order failed due to insufficient inventory',
                        $order->getData('xendit_transaction_id')
                    )
                );
            }

            foreach ($orderItem->getChildrenItems() as $childItem) {
                /** @var ProductInterface $childItemProduct */
                $childItemProduct = $childItem->getProduct();
                $productStockQty[$childItemProduct->getId()] = $orderItem->getQtyCanceled();
                if (!$childItemProduct->isSalable()) {
                    throw new \Magento\Framework\Exception\LocalizedException(
                        __(
                            'Payment processed on Xendit (transaction_id %1). Order failed due to insufficient inventory',
                            $order->getData('xendit_transaction_id')
                        )
                    );
                }
            }
        }

        return $productStockQty;
    }

    /**
     * Used for revert a canceled order to a pending order
     *
     * @param Order $order
     * @return void
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws LocalizedException
     */
    public function revertCancelledOrderToPending(Order $order)
    {
        try {
            $orderState = Order::STATE_PENDING_PAYMENT;
            if ($this->canRevertOrderStatusToPending($order)) {
                try {
                    // Check order item qty before reduce when un-cancel
                    $productStockQty = $this->getOrderItemsQty($order);
                    // Reduce the stock if all good.
                    $this->reduceProductSockForSales($productStockQty, $order);
                } catch (Exception $e) {
                    $orderState = self::ORDER_STATUS_INSUFFICIENT_INVENTORY;
                    throw new \Magento\Framework\Exception\LocalizedException(
                        __(
                            'Payment processed on Xendit (transaction_id %1). Order failed due to insufficient inventory',
                            $order->getData('xendit_transaction_id')
                        )
                    );
                }

                // Un cancel order items
                foreach ($order->getAllVisibleItems() as $orderItem) {
                    foreach ($orderItem->getChildrenItems() as $childItem) {
                        $childItem->setQtyCanceled(0)
                            ->setTaxCanceled(0)
                            ->setDiscountTaxCompensationCanceled(0);
                    }
                    $orderItem->setQtyCanceled(0)
                        ->setTaxCanceled(0)
                        ->setDiscountTaxCompensationCanceled(0);
                }

                $this->orderRepository->save(
                    $this->revertOrderStatusToPending($order, $orderState)
                );
            }
        } catch (\Exception $e) {
            // cancel order if it has error
            $order->cancel();
            if ($orderState === self::ORDER_STATUS_INSUFFICIENT_INVENTORY) {
                $order->setStatus($orderState)
                    ->setState($orderState);
            }
            $order->addCommentToStatusHistory($e->getMessage());
            $this->orderRepository->save($order);

            // Make sure all items also canceled
            $this->setOrderItemsToCanceled($order);
            throw new \Magento\Framework\Exception\LocalizedException(
                __($e->getMessage())
            );
        }
    }

    /**
     * @param Order $order
     * @return bool
     */
    public function canRevertOrderStatusToPending(Order $order): bool
    {
        return $order->isCanceled() || $order->getState() == self::ORDER_STATUS_INSUFFICIENT_INVENTORY;
    }
}
