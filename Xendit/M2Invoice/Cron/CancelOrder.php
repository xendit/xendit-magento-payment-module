<?php

namespace Xendit\M2Invoice\Cron;

use Magento\Framework\App\State;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Sales\Model\OrderFactory;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

use Psr\Log\LoggerInterface;

class CancelOrder
{
    protected $logger;

    protected $orderCollectionFactory;

    protected $state;

    protected $orderFactory;

    protected $dateTime;

    public function __construct(
        State $state,
        LoggerInterface $logger,
        CollectionFactory $collectionFactory,
        OrderFactory $orderFactory,
        DateTime $dateTime
    ) {
        $this->logger = $logger;
        $this->orderCollectionFactory = $collectionFactory;
        $this->orderFactory = $orderFactory;
        $this->dateTime = $dateTime;
    }

    public function execute()
    {
        $collection = $this->orderCollectionFactory
            ->create()
            ->addAttributeToSelect('increment_id')
            ->addFieldToFilter('status', Order::STATE_PENDING_PAYMENT);

        foreach ($collection as $doc) {
            $order = $this->orderFactory->create()->loadByIncrementId($doc['increment_id']);
            $payment = $order->getPayment();

            $invoiceExpDate = $payment->getAdditionalInformation('xendit_invoice_exp_date');

            $date = $this->dateTime->timestamp($invoiceExpDate);
            $now = $this->dateTime->timestamp();

            if ($date < $now) {
                $order->setState(Order::STATE_CANCELED)
                    ->setStatus(Order::STATE_CANCELED)
                    ->addStatusHistoryComment("Xendit payment cancelled due to expired invoice");
            }
        }

        return $this;
	}
}