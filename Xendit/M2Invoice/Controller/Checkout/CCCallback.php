<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\DB\Transaction as DbTransaction;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Xendit\M2Invoice\Helper\ApiRequest;
use Xendit\M2Invoice\Helper\Checkout;
use Xendit\M2Invoice\Helper\Data;
use Xendit\M2Invoice\Logger\Logger as XenditLogger;

/**
 * This callback is for CC Subscription, both onepage & multishipping checkout.
 */
class CCCallback extends Action implements CsrfAwareActionInterface
{
    /**
     * @var JsonFactory
     */
    private $jsonResultFactory;

    /**
     * @var OrderFactory
     */
    private $orderFactory;

    /**
     * @var InvoiceService
     */
    private $invoiceService;

    /**
     * @var DbTransaction
     */
    private $dbTransaction;

    /**
     * @var XenditLogger
     */
    private $logger;

    /**
     * @var ApiRequest
     */
    private $apiHelper;

    /**
     * @var Checkout
     */
    private $checkoutHelper;

    /**
     * @var Data
     */
    private $dataHelper;

    public function __construct(
        Context $context,
        JsonFactory $jsonResultFactory,
        OrderFactory $orderFactory,
        InvoiceService $invoiceService,
        DbTransaction $dbTransaction,
        XenditLogger $logger,
        ApiRequest $apiHelper,
        Checkout $checkoutHelper,
        Data $dataHelper
    ) {
        parent::__construct($context);
        $this->jsonResultFactory = $jsonResultFactory;
        $this->orderFactory = $orderFactory;
        $this->invoiceService = $invoiceService;
        $this->dbTransaction = $dbTransaction;
        $this->logger = $logger;
        $this->apiHelper = $apiHelper;
        $this->checkoutHelper = $checkoutHelper;
        $this->dataHelper = $dataHelper;
    }
    
    public function execute()
    {
        try {
            $post = $this->getRequest()->getContent();
            $callbackPayload = json_decode($post, true);

            $this->logger->info("callbackPayload");
            $this->logger->info($post);

            if (
                !isset($callbackPayload['id']) ||
                !isset($callbackPayload['hp_token']) ||
                !isset($callbackPayload['order_number'])
            ) {
                $result = $this->jsonResultFactory->create();
                $result->setHttpResponseCode(\Magento\Framework\Webapi\Exception::HTTP_BAD_REQUEST);
                $result->setData([
                    'status' => __('ERROR'),
                    'message' => 'Callback body is invalid'
                ]);

                return $result;
            }
            $orderIds = explode('-', $callbackPayload['order_number']);
            $isMultishipping = ($this->getRequest()->getParam('type') === 'multishipping');
            $isError = false;

            $hostedPaymentId = $callbackPayload['id'];
            $hostedPaymentToken = $callbackPayload['hp_token'];
            $requestData = [
                'id' => $hostedPaymentId,
                'hp_token' => $hostedPaymentToken
            ];

            if ($isMultishipping) {
                $flag = true;

                foreach ($orderIds as $orderId) {
                    $order = $this->orderFactory->create();
                    $order->load($orderId);

                    if ($flag) { // complete hosted payment only once as status will be changed to USED
                        $hostedPayment = $this->getCompletedHostedPayment($requestData);
                        $flag = false;
                    }
                    
                    if (isset($hostedPayment['error_code'])) {
                        $isError = true;
                    } else {
                        if ($hostedPayment['order_number'] !== $callbackPayload['order_number']) {
                            $result = $this->jsonResultFactory->create();
                            $result->setHttpResponseCode(\Magento\Framework\Webapi\Exception::HTTP_BAD_REQUEST);
                            $result->setData([
                                'status' => __('ERROR'),
                                'message' => 'Hosted payment is not for this order'
                            ]);
    
                            return $result;
                        }
    
                        if ($hostedPayment['paid_amount'] != $hostedPayment['amount']) {
                            $order->setBaseDiscountAmount($hostedPayment['paid_amount'] - $hostedPayment['amount']);
                            $order->setDiscountAmount($hostedPayment['paid_amount'] - $hostedPayment['amount']);
                            $order->save();
            
                            $order->setBaseGrandTotal($order->getBaseGrandTotal() + $order->getBaseDiscountAmount());
                            $order->setGrandTotal($order->getGrandTotal() + $order->getDiscountAmount());
                            $order->save();
                        }

                        $payment = $order->getPayment();
                        $payment->setAdditionalInformation('token_id', $hostedPayment['token_id']);
        
                        $this->processSuccessfulTransaction(
                            $order,
                            $payment,
                            'Xendit Credit Card payment completed. Transaction ID: ',
                            $hostedPayment['charge_id']
                        );
                    }
                }
            } else {
                $order = $this->getOrderById($orderIds[0]);

                $hostedPayment = $this->getCompletedHostedPayment($requestData);
                
                if (isset($hostedPayment['error_code'])) {
                    $isError = true;
                } else {
                    if ($hostedPayment['order_number'] !== $callbackPayload['order_number']) {
                        $result = $this->jsonResultFactory->create();
                        $result->setHttpResponseCode(\Magento\Framework\Webapi\Exception::HTTP_BAD_REQUEST);
                        $result->setData([
                            'status' => __('ERROR'),
                            'message' => 'Hosted payment is not for this order'
                        ]);

                        return $result;
                    }

                    if ($hostedPayment['paid_amount'] != $hostedPayment['amount']) {
                        $order->setBaseDiscountAmount($hostedPayment['paid_amount'] - $hostedPayment['amount']);
                        $order->setDiscountAmount($hostedPayment['paid_amount'] - $hostedPayment['amount']);
                        $order->save();
        
                        $order->setBaseGrandTotal($order->getBaseGrandTotal() + $order->getBaseDiscountAmount());
                        $order->setGrandTotal($order->getGrandTotal() + $order->getDiscountAmount());
                        $order->save();
                    }

                    $payment = $order->getPayment();
                    $payment->setAdditionalInformation('token_id', $hostedPayment['token_id']);
    
                    $this->processSuccessfulTransaction(
                        $order,
                        $payment,
                        'Xendit Credit Card payment completed. Transaction ID: ',
                        $hostedPayment['charge_id']
                    );
                }
            }

            $result = $this->jsonResultFactory->create();

            if ($isError) {
                $result->setHttpResponseCode(\Magento\Framework\Webapi\Exception::HTTP_BAD_REQUEST);
                $result->setData([
                    'status' => __('ERROR'),
                    'message' => 'Callback error: ' . $hostedPayment['error_code']
                ]);
            } else {
                $result->setData([
                    'status' => __('SUCCESS'),
                    'message' => 'CC paid'
                ]);
            }
            return $result;
            
        } catch (\Exception $e) {
            $result = $this->jsonResultFactory->create();
            $result->setHttpResponseCode(\Magento\Framework\Webapi\Exception::HTTP_BAD_REQUEST);
            $result->setData([
                'status' => __('ERROR'),
                'message' => $e->getMessage()
            ]);

            return $result;
        }
    }

    private function getCompletedHostedPayment($requestData)
    {
        $url = $this->dataHelper->getCheckoutUrl() . "/payment/xendit/hosted-payments/" . $requestData['id'] . "?hp_token=" . $requestData['hp_token'] . '&statuses[]=COMPLETED';
        $method = \Zend\Http\Request::METHOD_GET;

        $this->logger->info("getCompletedHostedPayment");
        $this->logger->info($url);

        try {
            $hostedPayment = $this->apiHelper->request(
                $url, $method
            );
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            throw new LocalizedException(
                __($e->getMessage())
            );
        }

        return $hostedPayment;
    }

    private function processSuccessfulTransaction($order, $payment, $paymentMessage, $transactionId)
    {
        $orderState = Order::STATE_PROCESSING;
        $order->setState($orderState)
            ->setStatus($orderState)
            ->addStatusHistoryComment("$paymentMessage $transactionId");

        $order->save();

        $payment->setTransactionId($transactionId);
        $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE, null, true);

        $this->invoiceOrder($order, $transactionId);
    }

    private function invoiceOrder($order, $transactionId)
    {
        $this->logger->info("invoiceOrder");
        if (!$order->canInvoice()) {
            throw new LocalizedException(
                __('Cannot create an invoice.')
            );
        }

        $invoice = $this->invoiceService->prepareInvoice($order);

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
        if ($transactionId) {
            $invoice->setTransactionId($transactionId);
        }
        $invoice->setRequestedCaptureCase(Order\Invoice::CAPTURE_OFFLINE);
        $invoice->register();
        $transaction = $this->dbTransaction->addObject($invoice)->addObject($invoice->getOrder());
        $transaction->save();
    }

    /**
     * @param $orderId
     * @return Order|null
     */
    protected function getOrderById($orderId)
    {
        $order = $this->orderFactory->create()->load($orderId);
        if (!$order->getId()) {
            $order = $this->orderFactory->create()->loadByIncrementId($orderId);
            if (!$order->getId()) {
                return null;
            }
        }
        return $order;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
