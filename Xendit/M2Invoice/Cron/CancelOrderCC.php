<?php

namespace Xendit\M2Invoice\Cron;

use Xendit\M2Invoice\Helper\Data;
use Xendit\M2Invoice\Helper\ApiRequest;
use Magento\Framework\App\State;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Sales\Model\OrderFactory;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

use Psr\Log\LoggerInterface;

class CancelOrderCC
{
    protected $logger;

    protected $orderCollectionFactory;

    protected $state;

    protected $orderFactory;

    protected $dateTime;

    protected $dataHelper;

    protected $apiRequestHelper;

    public function __construct(
        State $state,
        LoggerInterface $logger,
        CollectionFactory $collectionFactory,
        OrderFactory $orderFactory,
        DateTime $dateTime,
        Data $data,
        ApiRequest $apiRequest
    ) {
        $this->logger = $logger;
        $this->orderCollectionFactory = $collectionFactory;
        $this->orderFactory = $orderFactory;
        $this->dateTime = $dateTime;
        $this->dataHelper = $data;
        $this->apiRequestHelper = $apiRequest;
    }

    public function execute()
    {
        $collection = $this->orderCollectionFactory
            ->create()
            ->addAttributeToSelect('increment_id')
            ->addFieldToFilter('status', Order::STATE_PAYMENT_REVIEW);

        $bulkCancelData = array();

        foreach ($collection as $doc) {
            $order = $this->orderFactory->create()->loadByIncrementId($doc['increment_id']);
            $payment = $order->getPayment();

            $paymentGateway = $payment->getAdditionalInformation('payment_gateway');
            $hosted3DSId = $payment->getAdditionalInformation('xendit_hosted_3ds_id');
            $creationTime = $order->getCreatedAt();

            if ('xendit' !== $paymentGateway) {
                continue;
            }

            if (strtotime($creationTime) < strtotime('-1 day')) {
                $order->setState(Order::STATE_CANCELED)
                    ->setStatus(Order::STATE_CANCELED)
                    ->addStatusHistoryComment("Xendit payment cancelled due to stuck on payment review for 24 hours");

                $bulkCancelData[] = array(
                    'id' => $hosted3DSId,
                    'expiry_date' => $this->dateTime->timestamp(),
                    'order_number' => $order->getId(),
                    'amount' => $order->getGrandTotal()
                );

                $order->save();
            }
        }

        if (!empty($bulkCancelData)) {
            $this->trackCancellation($bulkCancelData);
        }

        return $this;
	}

	private function trackCancellation($data)
    {
        $bulkCancelUrl = $this->dataHelper->getCheckoutUrl() . "/payment/xendit/invoice/bulk-cancel";
        $bulkCancelMethod = \Zend\Http\Request::METHOD_POST;
        $bulkCancelData = array(
            'invoice_data' => json_encode($data)
        );

        try {
            $this->apiRequestHelper->request($bulkCancelUrl, $bulkCancelMethod, $bulkCancelData);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            throw new LocalizedException(
                $e->getMessage()
            );
        }
    }
}