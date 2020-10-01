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

class CancelOrder
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
            ->addFieldToFilter('status', Order::STATE_PENDING_PAYMENT);

        $bulkCancelData = array();

        foreach ($collection as $doc) {
            $order = $this->orderFactory->create()->loadByIncrementId($doc['increment_id']);
            $payment = $order->getPayment();

            $invoiceId = $payment->getAdditionalInformation('xendit_invoice_id');
            $invoiceExpDate = $payment->getAdditionalInformation('xendit_invoice_exp_date');
            $paymentGateway = $payment->getAdditionalInformation('payment_gateway');

            if ('xendit' !== $paymentGateway) {
                continue;
            }

            $date = $this->dateTime->timestamp($invoiceExpDate);
            $now = $this->dateTime->timestamp();

            if ($date < $now) {
                $order->setState(Order::STATE_CANCELED)
                    ->setStatus(Order::STATE_CANCELED)
                    ->addStatusHistoryComment("Xendit payment cancelled due to expired invoice");

                $bulkCancelData[] = array(
                    'id' => $invoiceId,
                    'expiry_date' => $invoiceExpDate,
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