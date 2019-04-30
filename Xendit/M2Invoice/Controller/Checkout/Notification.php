<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Sales\Model\Order;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Action\Action;
use Xendit\M2Invoice\Helper\Checkout;
use Magento\Sales\Model\OrderFactory;

class Notification extends Action implements CsrfAwareActionInterface
{
    protected $jsonResultFactory;

    protected $checkoutHelper;

    protected $messageManager;

    protected $orderFactory;

    public function __construct(
        Context $context,
        JsonFactory $jsonResultFactory,
        Checkout $checkoutHelper,
        OrderFactory $orderFactory
    ) {
        parent::__construct($context);
        $this->jsonResultFactory = $jsonResultFactory;
        $this->checkoutHelper = $checkoutHelper;
        $this->messageManager = $context->getMessageManager();
        $this->orderFactory = $orderFactory;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    public function execute()
    {
        $post = $this->getRequest()->getContent();
        $decodedPost = json_decode($post, true);

        if ( !isset($decodedPost['description']) || !isset($decodedPost['id']) || !isset($decodedPost['status']) ) {
            $result = $this->jsonResultFactory->create();
            /** You may introduce your own constants for this custom REST API */
            $result->setHttpResponseCode(\Magento\Framework\Webapi\Exception::HTTP_BAD_REQUEST);
            $result->setData([
                'status' => __('ERROR'),
                'message' => 'Callback body is invalid'
            ]);

            return $result;
        }

        $paymentStatus = $decodedPost['status'];
        $orderId = $decodedPost['description'];
        $transactionId = $decodedPost['id'];

        $order = $this->getOrderById($orderId);
        if (!$order) {
            $result = $this->jsonResultFactory->create();
            /** You may introduce your own constants for this custom REST API */
            $result->setHttpResponseCode(\Magento\Framework\Webapi\Exception::HTTP_BAD_REQUEST);
            $result->setData([
                'status' => __('ERROR'),
                'message' => 'Order not found'
            ]);

            return $result;
        }

        if ($paymentStatus === 'PAID') {
            $orderState = Order::STATE_PROCESSING;

            $order->setState($orderState)
                ->setStatus($orderState)
                ->addStatusHistoryComment("Xendit payment completed. Transaction ID: $transactionId");
            
                $payment = $order->getPayment();
                $payment->setTransactionId($transactionId);
                $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE, null, true);

            $order->save();

            $this->invoiceOrder($order, $transactionId);
            
            $this->getMessageManager()->addSuccessMessage(__("Your payment with Xendit is completed"));
        } else {
            $this->getCheckoutHelper()->cancelCurrentOrder("Order #".($order->getId())." was rejected by Xendit. Transaction #$transactionId.");
            $this->getCheckoutHelper()->restoreQuote(); //restore cart
            $this->getMessageManager()->addErrorMessage(__("There was a failure in the Xendit payment"));
        }
    }

    private function invoiceOrder($order, $transactionId)
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

    protected function getCheckoutHelper()
    {
        return $this->checkoutHelper;
    }

    protected function getMessageManager()
    {
        return $this->messageManager;
    }

    protected function getOrderById($orderId)
    {
        $order = $this->orderFactory->create()->loadByIncrementId($orderId);
        if (!$order->getId()) {
            return null;
        }
        return $order;
    }
}