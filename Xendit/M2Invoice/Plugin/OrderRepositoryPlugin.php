<?php

namespace Xendit\M2Invoice\Plugin;

use Magento\Sales\Api\Data\OrderExtensionFactory;
use Magento\Sales\Api\Data\OrderExtensionInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Class OrderRepositoryPlugin
 */
class OrderRepositoryPlugin
{
    /**
     * Xendit Invoice ID Column
     */
    const XENDIT_INVOICE_ID = 'xendit_invoice_id';

    /**
     * Xendit expiration date
     */
    const XENDIT_INVOICE_EXPIRATION_DATE = 'xendit_invoice_exp_date';

    /**
     * Order Extension Attributes Factory
     *
     * @var OrderExtensionFactory
     */
    protected $extensionFactory;

    /**
     * OrderRepositoryPlugin constructor
     *
     * @param OrderExtensionFactory $extensionFactory
     */
    public function __construct(OrderExtensionFactory $extensionFactory)
    {
        $this->extensionFactory = $extensionFactory;
    }

    /**
     * Add "xendit_invoice_id" and "xendit_invoice_exp_date" extension attribute to order data
     * object to make it accessible in API data
     *
     * @param OrderRepositoryInterface $subject
     * @param OrderInterface $order
     *
     * @return OrderInterface
     */
    public function afterGet(OrderRepositoryInterface $subject, OrderInterface $order)
    {
        $xenditInvoiceId = $order->getData(self::XENDIT_INVOICE_ID);
        $xenditInvoiceExpDate = $order->getData(self::XENDIT_INVOICE_EXPIRATION_DATE);
        $extensionAttributes = $order->getExtensionAttributes();
        $extensionAttributes = $extensionAttributes ? $extensionAttributes : $this->extensionFactory->create();
        $extensionAttributes->setXenditInvoiceId($xenditInvoiceId);
        $extensionAttributes->setXenditInvoiceExpDate($xenditInvoiceExpDate);
        $order->setExtensionAttributes($extensionAttributes);

        return $order;
    }

    /**
     * Add "xendit_invoice_id" and "xendit_invoice_exp_date" extension attribute to order data
     * object to make it accessible in API data
     *
     * @param OrderRepositoryInterface $subject
     * @param OrderSearchResultInterface $searchResult
     *
     * @return OrderSearchResultInterface
     */
    public function afterGetList(OrderRepositoryInterface $subject, OrderSearchResultInterface $searchResult)
    {
        $orders = $searchResult->getItems();

        foreach ($orders as &$order) {
            $xenditInvoiceId = $order->getData(self::XENDIT_INVOICE_ID);
            $xenditInvoiceExpDate = $order->getData(self::XENDIT_INVOICE_EXPIRATION_DATE);
            $extensionAttributes = $order->getExtensionAttributes();
            $extensionAttributes = $extensionAttributes ? $extensionAttributes : $this->extensionFactory->create();
            $extensionAttributes->setXenditInvoiceId($xenditInvoiceId);
            $extensionAttributes->setXenditInvoiceExpDate($xenditInvoiceExpDate);
            $order->setExtensionAttributes($extensionAttributes);
        }

        return $searchResult;
    }
}