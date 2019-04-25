<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Framework\App\Action\Action;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Model\OrderFactory;
use Psr\Log\LoggerInterface;
use Xendit\M2Invoice\Helper\Data;

abstract class AbstractAction extends Action
{
    const LOG_FILE = 'xendit.log';

    private $_checkoutSession;

    private $_context;

    private $_orderFactory;

    private $_logger;

    private $_dataHelper;

    public function __construct(
        Session $checkoutSession,
        Context $context,
        OrderFactory $orderFactory,
        LoggerInterface $logger,
        Data $dataHelper
    ) {
        parent::__construct($context);

        $this->_checkoutSession = $checkoutSession;
        $this->_context = $context;
        $this->_orderFactory = $orderFactory;
        $this->_logger = $logger;
        $this->_dataHelper = $dataHelper;
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
}