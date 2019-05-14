<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Checkout\Model\Session;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use Xendit\M2Invoice\Helper\Data;
use Xendit\M2Invoice\Helper\Crypto;
use Xendit\M2Invoice\Helper\Checkout;
use Xendit\M2Invoice\Helper\ApiRequest;

abstract class AbstractAction extends Action
{
    const LOG_FILE = 'xendit.log';

    private $checkoutSession;

    private $context;

    private $orderFactory;

    private $logger;

    private $dataHelper;

    private $cryptoHelper;

    private $checkoutHelper;

    protected $messageManager;

    private $orderRepo;

    private $apiHelper;

    protected $resultRedirectFactory;

    public function __construct(
        Session $checkoutSession,
        Context $context,
        OrderFactory $orderFactory,
        LoggerInterface $logger,
        Data $dataHelper,
        Crypto $cryptoHelper,
        Checkout $checkoutHelper,
        OrderRepositoryInterface $orderRepo,
        ApiRequest $apiHelper
    ) {
        parent::__construct($context);

        $this->checkoutSession = $checkoutSession;
        $this->context = $context;
        $this->orderFactory = $orderFactory;
        $this->logger = $logger;
        $this->dataHelper = $dataHelper;
        $this->cryptoHelper = $cryptoHelper;
        $this->checkoutHelper = $checkoutHelper;
        $this->messageManager = $context->getMessageManager();
        $this->orderRepo = $orderRepo;
        $this->apiHelper = $apiHelper;
        $this->resultRedirectFactory = $context->getResultRedirectFactory();
    }

    protected function getContext()
    {
        return $this->context;
    }

    protected function getCheckoutSession()
    {
        return $this->checkoutSession;
    }

    protected function getOrderFactory()
    {
        return $this->orderFactory;
    }

    protected function getLogger()
    {
        return $this->logger;
    }
    
    protected function getOrder()
    {
        $orderId = $this->checkoutSession->getLastRealOrderId();

        if (!isset($orderId)) {
            return null;
        }

        return $this->getOrderById($orderId);
    }

    protected function getOrderById($orderId)
    {
        $order = $this->orderFactory->create()->loadByIncrementId($orderId);

        if (!$order->getId()) {
            return null;
        }

        return $order;
    }

    protected function getObjectManager()
    {
        return \Magento\Framework\App\ObjectManager::getInstance();
    }

    protected function getDataHelper()
    {
        return $this->dataHelper;
    }

    protected function getCryptoHelper()
    {
        return $this->cryptoHelper;
    }

    protected function getCheckoutHelper()
    {
        return $this->checkoutHelper;
    }

    protected function getMessageManager()
    {
        return $this->messageManager;
    }

    protected function getOrderRepo()
    {
        return $this->orderRepo;
    }

    protected function getApiHelper()
    {
        return $this->apiHelper;
    }

    protected function invoiceOrder($order, $transactionId)
    {
        if (!$order->canInvoice()) {
            throw new LocalizedException(
                __('Cannot create an invoice.')
            );
        }
        
        $invoice = $this->getObjectManager()
            ->create('Magento\Sales\Model\Service\InvoiceService')
            ->prepareInvoice($order);
        
        if (!$invoice->getTotalQty()) {
            throw new LocalizedException(
                __('You can\'t create an invoice without products.')
            );
        }
        
        /*
         * Look Magento/Sales/Model/Order/Invoice.register() for CAPTURE_OFFLINE explanation.
         * Basically, if !config/can_capture and config/is_gateway and CAPTURE_OFFLINE and 
         * Payment.IsTransactionPending => pay (Invoice.STATE = STATE_PAID...)
         */
        $invoice->setTransactionId($transactionId);
        $invoice->setRequestedCaptureCase(Order\Invoice::CAPTURE_OFFLINE);
        $invoice->register();
        $transaction = $this->getObjectManager()->create('Magento\Framework\DB\Transaction')
            ->addObject($invoice)
            ->addObject($invoice->getOrder());
        $transaction->save();
    }

    protected function getRedirectFactory()
    {
        return $this->resultRedirectFactory;
    }
}
