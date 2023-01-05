<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
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

        try {
            $orderIds = $this->getMultiShippingOrderIds();
            if (empty($orderIds)) {
                $message = __('The order not exist');
                $this->getLogger()->info($message);
                return $this->redirectToCart($message);
            }

            foreach ($orderIds as $orderId) {
                $order = $this->getOrderRepo()->get($orderId);
                if (!$this->orderValidToCreateXenditInvoice($order)) {
                    $message = __('Order processed');
                    $this->getLogger()->info($message);
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
                foreach ($orderItems as $orderItem) {
                    $item = [];
                    $product = $orderItem->getProduct();
                    $item['reference_id'] = $product->getId();
                    $item['name'] = $product->getName();
                    $item['category'] = $this->getDataHelper()->extractProductCategoryName($product);
                    $item['price'] = $product->getPrice();
                    $item['type'] = 'PRODUCT';
                    $item['url'] = $product->getProductUrl() ?: 'https://xendit.co/';
                    $item['quantity'] = (int)$orderItem->getQtyOrdered();
                    $items[] = (object)$item;
                }

                if (empty($customerObject)) {
                    $customerObject = $this->getDataHelper()->extractXenditInvoiceCustomerFromOrder($order);
                }
            }

            if ($orderProcessed) {
                return $this->_redirect('multishipping/checkout/success');
            }

            $rawOrderIds = implode('-', $orderIds);
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

            if (!empty($invoice) && isset($invoice['error_code'])) {
                $message = $this->getErrorHandler()->mapInvoiceErrorCode($invoice['error_code']);
                // cancel order and redirect to cart
                return $this->processFailedPayment($orderIds, $message);
            }

            $this->addInvoiceData($orders, $invoice);

            $redirectUrl = $this->getXenditRedirectUrl($invoice, $preferredMethod);
            $resultRedirect = $this->getRedirectFactory()->create();
            $resultRedirect->setUrl($redirectUrl);
            return $resultRedirect;
        } catch (\Throwable $e) {
            $message = 'Exception caught on xendit/checkout/redirect: ' . $e->getMessage();
            $this->getLogger()->info($message);

            // log metric error
            $this->metricHelper->sendMetric(
                'magento2_checkout',
                [
                    'type' => 'error',
                    'payment_method' => $preferredMethod,
                    'error_message' => $e->getMessage()
                ]
            );

            return $this->redirectToCart($message);
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
                return $this->getApiHelper()->request(
                    $invoiceUrl,
                    $invoiceMethod,
                    $requestData,
                    false,
                    $requestData['preferred_method']
                );
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
     */
    private function addInvoiceData(array $orders, array $invoice)
    {
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
}
