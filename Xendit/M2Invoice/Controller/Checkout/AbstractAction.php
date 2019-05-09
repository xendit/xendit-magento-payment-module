<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Framework\App\Action\Action;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Model\OrderFactory;
use Psr\Log\LoggerInterface;
use Xendit\M2Invoice\Helper\Data;
use Xendit\M2Invoice\Helper\Crypto;
use Xendit\M2Invoice\Helper\Checkout;
use Magento\Sales\Api\OrderRepositoryInterface;
use Xendit\M2Invoice\Helper\ApiRequest;
use Magento\Sales\Model\Order;

abstract class AbstractAction extends Action
{
    const LOG_FILE = 'xendit.log';

    private $_checkoutSession;

    private $_context;

    private $_orderFactory;

    private $_logger;

    private $_dataHelper;

    private $_cryptoHelper;

    private $_checkoutHelper;

    private $_messageManager;

    private $_orderRepo;

    private $_apiHelper;

    private $_resultRedirectFactory;

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

        $this->_checkoutSession = $checkoutSession;
        $this->_context = $context;
        $this->_orderFactory = $orderFactory;
        $this->_logger = $logger;
        $this->_dataHelper = $dataHelper;
        $this->_cryptoHelper = $cryptoHelper;
        $this->_checkoutHelper = $checkoutHelper;
        $this->_messageManager = $context->getMessageManager();
        $this->_orderRepo = $orderRepo;
        $this->_apiHelper = $apiHelper;
        $this->_resultRedirectFactory = $context->getResultRedirectFactory();
    }

    protected function getContext()
    {
        return $this->_context;
    }

    protected function getCheckoutSession()
    {
        return $this->_checkoutSession;
    }

    protected function getOrderFactory()
    {
        return $this->_orderFactory;
    }

    protected function getLogger()
    {
        return $this->_logger;
    }
    
    protected function getOrder()
    {
        $orderId = $this->_checkoutSession->getLastRealOrderId();

        if (!isset($orderId)) {
            return null;
        }

        return $this->getOrderById($orderId);
    }

    protected function getOrderById($orderId)
    {
        $order = $this->_orderFactory->create()->loadByIncrementId($orderId);

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
        return $this->_dataHelper;
    }

    protected function getCryptoHelper()
    {
        return $this->_cryptoHelper;
    }

    protected function getCheckoutHelper()
    {
        return $this->_checkoutHelper;
    }

    protected function getMessageManager()
    {
        return $this->_messageManager;
    }

    protected function getOrderRepo()
    {
        return $this->_orderRepo;
    }

    protected function getApiHelper()
    {
        return $this->_apiHelper;
    }

    protected function invoiceOrder($order, $transactionId)
    {
        if(!$order->canInvoice()){
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Cannot create an invoice.')
            );
        }
        
        $invoice = $this->getObjectManager()
            ->create('Magento\Sales\Model\Service\InvoiceService')
            ->prepareInvoice($order);
        
        if (!$invoice->getTotalQty()) {
            throw new \Magento\Framework\Exception\LocalizedException(
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
        return $this->_resultRedirectFactory;
    }
}