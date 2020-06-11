<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\Phrase;
use Xendit\M2Invoice\Enum\LogDNALevel;
use Xendit\M2Invoice\Helper\ApiRequest;
use Xendit\M2Invoice\Helper\Checkout;
use Xendit\M2Invoice\Helper\Data;
use Xendit\M2Invoice\Helper\LogDNA;

class Notification extends Action implements CsrfAwareActionInterface
{
    private $jsonResultFactory;

    private $checkoutHelper;

    private $orderFactory;

    private $dataHelper;

    private $apiHelper;

    private $logDNA;

    public function __construct(
        Context $context,
        JsonFactory $jsonResultFactory,
        Checkout $checkoutHelper,
        OrderFactory $orderFactory,
        Data $dataHelper,
        ApiRequest $apiHelper,
        LogDNA $logDNA
    ) {
        parent::__construct($context);
        $this->jsonResultFactory = $jsonResultFactory;
        $this->checkoutHelper = $checkoutHelper;
        $this->orderFactory = $orderFactory;
        $this->dataHelper = $dataHelper;
        $this->apiHelper = $apiHelper;
        $this->logDNA = $logDNA;
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
        try {
            $post = $this->getRequest()->getContent();
            $callbackToken = $this->getRequest()->getHeader('X-CALLBACK-TOKEN');
            $decodedPost = json_decode($post, true);
            $isEwallet = false;

            if (!empty($decodedPost['ewallet_type'])) {
                $isEwallet = true;

                if (!$decodedPost['external_id']) {
                    $result = $this->jsonResultFactory->create();
                    /** You may introduce your own constants for this custom REST API */
                    $result->setHttpResponseCode(\Magento\Framework\Webapi\Exception::HTTP_BAD_REQUEST);
                    $result->setData([
                        'status' => __('ERROR'),
                        'message' => 'Callback external_id is invalid'
                    ]);

                    return $result;
                }
            } else if (!isset($decodedPost['description']) || !isset($decodedPost['id'])) {
                $result = $this->jsonResultFactory->create();
                /** You may introduce your own constants for this custom REST API */
                $result->setHttpResponseCode(\Magento\Framework\Webapi\Exception::HTTP_BAD_REQUEST);
                $result->setData([
                    'status' => __('ERROR'),
                    'message' => 'Callback body is invalid'
                ]);

                return $result;
            }

            if ($isEwallet) {
                // default code if API doesn't send failure_code
                $failureCode = 'UNKNOWN_ERROR';
                if (isset($decodedPost['failure_code'])) {
                    $failureCode = $decodedPost['failure_code'];
                }

                $extIdPrefix = $this->dataHelper->getExternalIdPrefix();
                $orderId = ltrim($decodedPost['external_id'], $extIdPrefix);

                // standalone OVO can only be single checkout
                $orderIds = [$orderId];
            } else {
                $orderId = $decodedPost['description'];
                $orderIds = explode("-", $orderId);
            }

            $transactionId = $decodedPost['id'];
            $isMultishipping = (count($orderIds) > 1) ? true : false;

            if (!empty($callbackToken)) {
                $result = $this->jsonResultFactory->create();
                /** You may introduce your own constants for this custom REST API */
                $result->setHttpResponseCode(\Magento\Framework\Webapi\Exception::HTTP_FORBIDDEN);
                $result->setData([
                    'status' => __('ERROR'),
                    'message' => 'Unauthorized callback request'
                ]);

                return $result;
            }

            $invoice = $this->getXenditInvoice($transactionId);

            if( $isMultishipping ) {
                foreach ($orderIds as $key => $value) {
                    $result = $this->checkOrder($value, $isEwallet, $decodedPost, $invoice, $orderId);
                }
                
                return $result;
            } else {
                return $this->checkOrder($orderId, $isEwallet, $decodedPost, $invoice, $orderId);
            }
        } catch (\Exception $e) {
            $message = "Error invoice callback: " . $e->getMessage();
            $this->logDNA->log(LogDNALevel::ERROR, $message, $decodedPost);

            $result = $this->jsonResultFactory->create();
            /** You may introduce your own constants for this custom REST API */
            $result->setHttpResponseCode(\Magento\Framework\Webapi\Exception::HTTP_BAD_REQUEST);
            $result->setData([
                    'status' => __('ERROR'),
                    'message' => $message
                ]);

            return $result;
        }
    }
    
    private function checkOrder($orderId, $isEwallet, $decodedPost, $invoice, $callbackDescription) {
        $order = $this->orderFactory->create();
        $order->load($orderId);
        $transactionId = $decodedPost['id'];

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

        if ($isEwallet) {
            $order = $this->getOrderById($orderId);

            if ($order->getState() === Order::STATE_PENDING_PAYMENT || $order->getState() === Order::STATE_PAYMENT_REVIEW) {
                //get ewallet payment status
                $paymentStatus = $this->getEwalletStatus($decodedPost['ewallet_type'], $decodedPost['external_id']);
            } else {
                $result = $this->jsonResultFactory->create();
                $result->setData([
                    'status' => __('SUCCESS'),
                    'message' => 'eWallet transaction has been completed successfully'
                ]);

                return $result;
            }
        } else {
            $paymentStatus = $invoice['status'];
            $invoiceOrderId = $invoice['description'];

            if ($invoiceOrderId !== $callbackDescription) {
                $result = $this->jsonResultFactory->create();
                /** You may introduce your own constants for this custom REST API */
                $result->setHttpResponseCode(\Magento\Framework\Webapi\Exception::HTTP_BAD_REQUEST);
                $result->setData([
                    'status' => __('ERROR'),
                    'message' => 'Invoice is not for this order'
                ]);

                return $result;
            }
        }

        $statusList = array('PAID', 'SETTLED', 'COMPLETED');
        if (in_array($paymentStatus, $statusList)) {
            $orderState = Order::STATE_PROCESSING;

            $order->setState($orderState)
                ->setStatus($orderState)
                ->addStatusHistoryComment("Xendit payment completed. Transaction ID: $transactionId");

            $payment = $order->getPayment();
            $payment->setTransactionId($transactionId);
            $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE, null, true);

            if ($isEwallet) {
                $payment->setAdditionalInformation('xendit_ewallet_id', $transactionId);
                $payment->save();
            }

            $order->save();

            $this->invoiceOrder($order, $transactionId);

            $result = $this->jsonResultFactory->create();
            $result->setData([
                'status' => __('SUCCESS'),
                'message' => ($isEwallet ? 'eWallet paid' : 'Invoice paid')
            ]);
        } else { //FAILED or EXPIRED
            $orderState = Order::STATE_CANCELED;

            if ($order->getStatus() != $orderState) {
                $this->getCheckoutHelper()->cancelCurrentOrder(
                    "Order #".($order->getId())." was rejected by Xendit. Transaction #$transactionId."
                );
                $this->getCheckoutHelper()->restoreQuote(); //restore cart
            }

            if ($isEwallet) {
                $order  ->setState($orderState)
                        ->setStatus($orderState);
                $order  ->save();
                
                $payment = $order->getPayment();
                $payment->setAdditionalInformation('xendit_ewallet_failure_code', $failureCode);
                $payment->save();
            }

            $result = $this->jsonResultFactory->create();
            $result->setData([
                'status' => __('FAILED'),
                'message' => ($isEwallet ? 'eWallet not paid' : 'Invoice not paid')
            ]);
        }

        return $result;
    }
    
    private function getXenditInvoice($invoiceId)
    {
        $invoiceUrl = $this->dataHelper->getCheckoutUrl() . "/payment/xendit/invoice/$invoiceId";
        $invoiceMethod = \Zend\Http\Request::METHOD_GET;

        try {
            $invoice = $this->apiHelper->request($invoiceUrl, $invoiceMethod);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            throw new LocalizedException(
                new Phrase($e->getMessage())
            );
        }

        return $invoice;
    }

    private function getEwalletStatus($ewalletType, $externalId)
    {
        $ewalletUrl = $this->dataHelper->getCheckoutUrl() . "/payment/xendit/ewallets?ewallet_type=".$ewalletType."&external_id=".$externalId;
        $ewalletMethod = \Zend\Http\Request::METHOD_GET;

        try {
            $response = $this->apiHelper->request($ewalletUrl, $ewalletMethod);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            throw new LocalizedException(
                new Phrase($e->getMessage())
            );
        }

        if ($ewalletType == 'DANA') {
            $response['status'] = $response['payment_status'];
        }

        $statusList = array("COMPLETED", "PAID", "SUCCESS_COMPLETED"); //OVO, DANA, LINKAJA
        if (in_array($response['status'], $statusList)) {
            return "COMPLETED";
        }
        
        return $response['status'];
    }

    private function invoiceOrder($order, $transactionId)
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

    protected function getCheckoutHelper()
    {
        return $this->checkoutHelper;
    }

    protected function getObjectManager()
    {
        return \Magento\Framework\App\ObjectManager::getInstance();
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
