<?php

namespace Xendit\M2Invoice\Cron;

use Magento\Framework\App\State;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;

use Psr\Log\LoggerInterface;

class CancelOrder
{
    protected $logger;

    protected $orderCollectionFactory;

    protected $state;

    public function __construct(
        State $state,
        LoggerInterface $logger,
        CollectionFactory $collectionFactory
    ) {
        $this->logger = $logger;
        $this->orderCollectionFactory = $collectionFactory;
    }

    public function execute()
    {
        // You Can filter collection as
        $collection = $this->orderCollectionFactory
            ->create()
            ->addAttributeToSelect('entity_id')
            ->addFieldToFilter('status', Order::STATE_PENDING_PAYMENT);

        $this->logger->info("CRON RUNNING!");

        foreach ($collection as $document) {
            if ("140" === $document['entity_id']) {
                $this->logger->info("inside the loop");
            }
        }

        $this->logger->info(print_r($collection->getData(), true));

        return $this;
	}
}