<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Sales\Api\Data\OrderItemInterface;
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
                $this->getLogger()->info(
                    'Redirect customer to Xendit',
                    ['order_id' => $order->getIncrementId(), 'redirect_url' => $redirectUrl]
                );
                $resultRedirect = $this->getRedirectFactory()->create();
                $resultRedirect->setUrl($redirectUrl);
                return $resultRedirect;
            } elseif ($order->getState() === Order::STATE_CANCELED) {
                $this->getLogger()->info('Order is already canceled', ['order_id' => $order->getIncrementId()]);

                $this->_redirect('checkout/cart');
            } else {
                $this->getLogger()->info('Order in unrecognized state', ['state' => $order->getState(), 'order_id' => $order->getIncrementId()]);
                $this->_redirect('checkout/cart');
            }
        } catch (\Throwable $e) {
            $errorMessage = sprintf('xendit/checkout/invoice failed: Order #%s - %s', $order->getIncrementId(), $e->getMessage());

            $this->getLogger()->error($errorMessage, ['order_id' => $order->getIncrementId()]);
            $this->getLogger()->debug('Exception caught on xendit/checkout/invoice: ' . $e->getMessage());
            $this->getLogger()->debug($e->getTraceAsString());

            // cancel order
            try {
                $this->cancelOrder($order, $e->getMessage());
                $this->metricHelper->sendMetric(
                    'magento2_checkout',
                    [
                        'type' => 'error',
                        'payment_method' => $this->getPreferredMethod($order),
                        'error_message' => $errorMessage
                    ]
                );
            } catch (\Exception $e) {
            }

            return $this->redirectToCart($e->getMessage());
        }
    }

    /**
     * @param Order $order
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getApiRequestData(Order $order)
    {
        $orderId = $order->getRealOrderId();
        $preferredMethod = $this->getPreferredMethod($order);
        $orderItems = $order->getAllItems();
        $items = [];
        /** @var OrderItemInterface $orderItem */
        foreach ($orderItems as $orderItem) {
            if (!empty($orderItem->getParentItem())) {
                continue;
            }

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

        // Extract order fees and send it to Xendit invoice
        $orderFees = $this->getDataHelper()->extractOrderFees($order);
        if (!empty($orderFees)) {
            $payload['fees'] = $orderFees;
        }

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
        $invoiceUrl = $this->getDataHelper()->getXenditApiUrl() . "/tpi/payment/xendit/invoice";
        $invoiceMethod = Request::METHOD_POST;
        $invoice = '';

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
            }
            if (isset($invoice['error_code'])) {
                $message = $this->getErrorHandler()->mapInvoiceErrorCode(
                    $invoice['error_code'],
                    str_replace('{{currency}}', $requestData['currency'], $invoice['message'] ?? '')
                );
                throw new LocalizedException(
                    new Phrase($message)
                );
            }
        } catch (LocalizedException $e) {
            throw new LocalizedException(
                new Phrase($e->getMessage())
            );
        }

        $this->getLogger()->info('createInvoice success', ['xendit_invoice' => $invoice]);
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
     * @throws LocalizedException
     */
    private function changePendingPaymentStatus(Order $order)
    {
        try {
            $order->setState(Order::STATE_PENDING_PAYMENT)->setStatus(Order::STATE_PENDING_PAYMENT);
            $order->addCommentToStatusHistory("Pending Xendit payment.");
            $this->getOrderRepo()->save($order);

            $this->getLogger()->info(
                'changePendingPaymentStatus success',
                ['order_id' => $order->getIncrementId()]
            );
        } catch (\Exception $e) {
            $this->getLogger()->error(
                sprintf('changePendingPaymentStatus failed: %s', $e->getMessage()),
                ['order_id' => $order->getIncrementId()]
            );

            throw new LocalizedException(
                new Phrase($e->getMessage())
            );
        }
    }

    /**
     * @param Order $order
     * @param array $invoice
     * @return void
     * @throws \Exception
     */
    private function addInvoiceData(Order $order, array $invoice)
    {
        try {
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
            $this->getLogger()->info(
                'addInvoiceData success',
                ['order_id' => $order->getIncrementId(), 'xendit_transaction_id' => $invoice['id']]
            );
        } catch (\Exception $e) {
            $this->getLogger()->error(
                sprintf('addInvoiceData failed: %s', $e->getMessage()),
                ['order_id' => $order->getIncrementId(), 'xendit_transaction_id' => $invoice['id']]
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
}
