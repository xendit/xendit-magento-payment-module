<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Order;
use Zend\Http\Request;

/**
 * Class InvoiceMultishipping
 * @package Xendit\M2Invoice\Controller\Checkout
 */
class InvoiceMultishipping extends AbstractAction
{
    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Redirect|\Magento\Framework\Controller\ResultInterface|null
     * @throws LocalizedException
     */
    public function execute()
    {
        $transactionAmount = 0;
        $orderProcessed = false;
        $orders = [];
        $items = [];
        $currency = '';
        $billingEmail = '';
        $customerObject = [];

        $orderIncrementIds = [];
        $preferredMethod = '';
        $orderIds = $this->getMultiShippingOrderIds();
        $rawOrderIds = implode('-', $orderIds);

        try {
            if (empty($orderIds)) {
                $message = __('The order not exist');
                $this->getLogger()->info($message, ['order_ids' => $orderIds]);
                return $this->redirectToCart($message);
            }

            foreach ($orderIds as $orderId) {
                $order = $this->getOrderRepo()->get($orderId);
                if (!$this->orderValidToCreateXenditInvoice($order)) {
                    $message = __('Order processed');
                    $this->getLogger()->info($message, ['order_id' => $orderId]);
                    return $this->redirectToCart($message);
                }

                if (empty($preferredMethod)) {
                    $preferredMethod = $this->getPreferredMethod($order);
                }
                $orderIncrementIds[] = $order->getRealOrderId();

                $orderState = $order->getState();
                if ($orderState === Order::STATE_PROCESSING && !$order->canInvoice()) {
                    $orderProcessed = true;
                    continue;
                }

                // Set order status to PENDING_PAYMENT
                $order->setState(Order::STATE_PENDING_PAYMENT)
                    ->setStatus(Order::STATE_PENDING_PAYMENT);
                $order->addCommentToStatusHistory("Pending Xendit payment.");

                $orders[] = $order;
                $this->getOrderRepo()->save($order);

                $transactionAmount += $order->getTotalDue();
                $billingEmail = empty($billingEmail) ? ($order->getCustomerEmail() ?: 'noreply@mail.com') : $billingEmail;
                $currency = $order->getBaseCurrencyCode();

                $orderItems = $order->getAllItems();
                /** @var OrderItemInterface $orderItem */
                foreach ($orderItems as $orderItem) {
                    $product = $orderItem->getProduct();
                    $item = [
                        'reference_id' => $product->getId(),
                        'name' => $orderItem->getName(),
                        'category' => $this->getDataHelper()->extractProductCategoryName($product),
                        'price' => $orderItem->getPrice(),
                        'type' => 'PRODUCT',
                        'url' => $product->getProductUrl() ?: 'https://xendit.co/',
                        'quantity' => (int)$orderItem->getQtyOrdered()
                    ];
                    $items[] = (object)$item;
                }

                if (empty($customerObject)) {
                    $customerObject = $this->getDataHelper()->extractXenditInvoiceCustomerFromOrder($order);
                }
            }

            if ($orderProcessed) {
                return $this->_redirect('multishipping/checkout/success');
            }

            $requestData = [
                'external_id' => $this->getDataHelper()->getExternalId($rawOrderIds),
                'payer_email' => $billingEmail,
                'description' => $rawOrderIds,
                'amount' => $transactionAmount,
                'currency' => $currency,
                'preferred_method' => $preferredMethod,
                'client_type' => 'INTEGRATION',
                'payment_methods' => json_encode([strtoupper($preferredMethod)]),
                'platform_callback_url' => $this->getXenditCallbackUrl(),
                'success_redirect_url' => $this->getDataHelper()->getSuccessUrl(true),
                'failure_redirect_url' => $this->getDataHelper()->getFailureUrl($orderIncrementIds),
                'items' => $items
            ];
            if (!empty($customerObject)) {
                $requestData['customer'] = $customerObject;
            }

            $invoice = $this->createInvoice($requestData);
            $this->addInvoiceData($orders, $invoice);

            $redirectUrl = $this->getXenditRedirectUrl($invoice, $preferredMethod);
            $this->getLogger()->info(
                'Redirect customer to Xendit',
                array_merge($this->getLogContext($orders, $invoice), ['redirect_url' => $redirectUrl])
            );

            $resultRedirect = $this->getRedirectFactory()->create();
            $resultRedirect->setUrl($redirectUrl);
            return $resultRedirect;
        } catch (\Throwable $e) {
            $logContext = $this->getLogContext($orders);
            $message = sprintf(
                'Exception caught on xendit/checkout/invoicemultishipping: Order_ids %s - %s',
                implode(', ', $logContext['order_ids'] ?? []),
                $e->getMessage()
            );
            $this->getLogger()->error($message, $logContext);

            try {
                // cancel orders
                $this->processFailedPayment($orderIds, $message);

                // log metric error
                $this->metricHelper->sendMetric(
                    'magento2_checkout',
                    [
                        'type' => 'error',
                        'payment_method' => $preferredMethod,
                        'error_message' => $message
                    ]
                );
            } catch (\Exception $ex) {
            }

            return $this->redirectToCart($e->getMessage());
        }
    }

    /**
     * @param $requestData
     * @return mixed
     * @throws LocalizedException
     */
    private function createInvoice($requestData)
    {
        $invoiceUrl = $this->getDataHelper()->getXenditApiUrl() . "/payment/xendit/invoice";
        $invoiceMethod = Request::METHOD_POST;

        try {
            if (isset($requestData['preferred_method'])) {
                $this->getLogger()->info('createInvoice start', ['data' => $requestData]);

                $invoice = $this->getApiHelper()->request(
                    $invoiceUrl,
                    $invoiceMethod,
                    $requestData,
                    false,
                    $requestData['preferred_method']
                );
                if (isset($invoice['error_code'])) {
                    $message = $this->getErrorHandler()->mapInvoiceErrorCode(
                        $invoice['error_code'],
                        str_replace('{{currency}}', $requestData['currency'], $invoice['message'] ?? '')
                    );
                    throw new LocalizedException(
                        new Phrase($message)
                    );
                }

                $this->getLogger()->info('createInvoice success', ['xendit_invoice' => $invoice]);
                return $invoice;
            }
        } catch (LocalizedException $e) {
            throw new LocalizedException(
                new Phrase($e->getMessage())
            );
        }
    }

    /**
     * @param $invoice
     * @param $preferredMethod
     * @return string
     */
    private function getXenditRedirectUrl($invoice, $preferredMethod)
    {
        return (isset($invoice['invoice_url'])) ? $invoice['invoice_url'] . "#$preferredMethod" : '';
    }

    /**
     * @param array $orders
     * @param array $invoice
     * @return void
     * @throws LocalizedException
     */
    private function addInvoiceData(array $orders, array $invoice)
    {
        try {
            foreach ($orders as $order) {
                $payment = $order->getPayment();
                $payment->setAdditionalInformation('payment_gateway', 'xendit');
                if (isset($invoice['id'])) {
                    $payment->setAdditionalInformation('xendit_invoice_id', $invoice['id']);
                    $order->setXenditTransactionId($invoice['id']);
                }
                if (isset($invoice['expiry_date'])) {
                    $payment->setAdditionalInformation('xendit_invoice_exp_date', $invoice['expiry_date']);
                }
                $this->getOrderRepo()->save($order);
            }

            $this->getLogger()->info('addInvoiceData success', $this->getLogContext($orders, $invoice));
        } catch (\Exception $e) {
            $this->getLogger()->error(
                sprintf('addInvoiceData failed: %s', $e->getMessage()),
                $this->getLogContext($orders, $invoice)
            );

            throw new LocalizedException(
                new Phrase($e->getMessage())
            );
        }
    }

    /**
     * @param $failureReason
     * @return \Magento\Framework\Controller\ResultInterface
     */
    private function redirectToCart($failureReason)
    {
        $failureReasonInsight = $this->getDataHelper()->failureReasonInsight($failureReason);
        $this->getMessageManager()->addErrorMessage(__(
            $failureReasonInsight
        ));
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($this->_url->getUrl('checkout/cart'), ['_secure' => false]);
        return $resultRedirect;
    }

    /**
     * @param array $orderIds
     * @param string $failureReason
     * @return void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function processFailedPayment(array $orderIds, string $failureReason = 'Unexpected Error with empty charge')
    {
        $this->getCheckoutHelper()->processOrdersFailedPayment($orderIds, $failureReason);

        $failureReasonInsight = $this->getDataHelper()->failureReasonInsight($failureReason);
        $this->getMessageManager()->addErrorMessage(__(
            $failureReasonInsight
        ));
        $this->_redirect('checkout/cart', ['_secure' => false]);
    }

    /**
     * @param array $orders
     * @param array $invoice
     * @return array
     */
    protected function getLogContext(array $orders, array $invoice = []): array
    {
        $context['order_ids'] = array_map(function (Order $order) {
            return $order->getIncrementId();
        }, $orders);
        if (!empty($invoice) && !empty($invoice['id'])) {
            $context['xendit_transaction_id'] = $invoice['id'];
        }
        return $context;
    }
}
