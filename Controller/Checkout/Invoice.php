<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Sales\Model\Order;
use Zend\Http\Request;

/**
 * Class Invoice
 * @package Xendit\M2Invoice\Controller\Checkout
 */
class Invoice extends AbstractAction
{
    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Redirect|\Magento\Framework\Controller\ResultInterface|void
     * @throws LocalizedException
     */
    public function execute()
    {
        $order = $this->getOrder();
        try {
            $apiData = $this->getApiRequestData($order);

            if ($order->getState() === Order::STATE_NEW) {
                $this->changePendingPaymentStatus($order);
                $invoice = $this->createInvoice($apiData);
                $this->addInvoiceData($order, $invoice);
                $redirectUrl = $this->getXenditRedirectUrl($invoice, $apiData['preferred_method']);

                $resultRedirect = $this->getRedirectFactory()->create();
                $resultRedirect->setUrl($redirectUrl);
                return $resultRedirect;
            } elseif ($order->getState() === Order::STATE_CANCELED) {
                $this->_redirect('checkout/cart');
            } else {
                $this->getLogger()->debug('Order in unrecognized state: ' . $order->getState());
                $this->_redirect('checkout/cart');
            }
        } catch (\Throwable $e) {
            $message = 'Exception caught on xendit/checkout/invoice: ' . $e->getMessage();

            $this->getLogger()->debug('Exception caught on xendit/checkout/invoice: ' . $message);
            $this->getLogger()->debug($e->getTraceAsString());

            $this->cancelOrder($order, $e->getMessage());

            // log metric error
            $this->metricHelper->sendMetric(
                'magento2_checkout',
                [
                    'type' => 'error',
                    'payment_method' => $this->getPreferredMethod(),
                    'error_message' => $e->getMessage()
                ]
            );

            return $this->redirectToCart($message);
        }
    }

    /**
     * @param $order
     * @return array|void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getApiRequestData($order)
    {
        if ($order == null) {
            $this->getLogger()->debug('Unable to get last order data from database');
            $this->_redirect('checkout/onepage/error', ['_secure' => false]);

            return;
        }

        $orderId = $order->getRealOrderId();
        $preferredMethod = $this->getRequest()->getParam('preferred_method');
        $orderItems = $order->getAllItems();
        $items = [];
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

        $amount = $order->getTotalDue();
        $customerObject = $this->getDataHelper()->extractXenditInvoiceCustomerFromOrder($order);

        $payload = [
            'external_id' => $this->getDataHelper()->getExternalId($orderId),
            'payer_email' => $order->getCustomerEmail(),
            'description' => $orderId,
            'amount' => $amount,
            'currency' => $order->getBaseCurrencyCode(),
            'preferred_method' => $preferredMethod,
            'client_type' => 'INTEGRATION',
            'payment_methods' => json_encode([strtoupper($preferredMethod)]),
            'platform_callback_url' => $this->getXenditCallbackUrl(),
            'success_redirect_url' => $this->getDataHelper()->getSuccessUrl(),
            'failure_redirect_url' => $this->getDataHelper()->getFailureUrl([$orderId]),
            'items' => $items
        ];

        if (!empty($customerObject)) {
            $payload['customer'] = $customerObject;
        }

        return $payload;
    }

    /**
     * @param $requestData
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function createInvoice($requestData)
    {
        $invoiceUrl = $this->getDataHelper()->getXenditApiUrl() . "/payment/xendit/invoice";
        $invoiceMethod = Request::METHOD_POST;
        $invoice = '';

        try {
            if (isset($requestData['preferred_method'])) {
                $invoice = $this->getApiHelper()->request(
                    $invoiceUrl,
                    $invoiceMethod,
                    $requestData,
                    false,
                    $requestData['preferred_method']
                );
            }
            if (isset($invoice['error_code'])) {
                $message = $this->getErrorHandler()->mapInvoiceErrorCode($invoice['error_code']);
                throw new LocalizedException(
                    new Phrase($message)
                );
            }
        } catch (LocalizedException $e) {
            throw new LocalizedException(
                new Phrase($e->getMessage())
            );
        }

        return $invoice;
    }

    /**
     * @param $invoice
     * @param $preferredMethod
     * @return string
     */
    private function getXenditRedirectUrl($invoice, $preferredMethod)
    {
        return $invoice['invoice_url'] . "#$preferredMethod";
    }

    /**
     * @param Order $order
     * @return void
     */
    private function changePendingPaymentStatus(Order $order)
    {
        $order->setState(Order::STATE_PENDING_PAYMENT)->setStatus(Order::STATE_PENDING_PAYMENT);
        $order->addCommentToStatusHistory("Pending Xendit payment.");
        $this->getOrderRepo()->save($order);
    }

    /**
     * @param Order $order
     * @param array $invoice
     * @return void
     */
    private function addInvoiceData(Order $order, array $invoice)
    {
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
}
