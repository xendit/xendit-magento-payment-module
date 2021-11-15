<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Sales\Model\Order;
use Magento\Framework\Phrase;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Controller\ResultFactory;
use Zend\Http\Request;

/**
 * Class Invoice
 * @package Xendit\M2Invoice\Controller\Checkout
 */
class Invoice extends AbstractAction
{
    public function execute()
    {
        try {
            $order = $this->getOrder();
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
        } catch (\Exception $e) {
            $message = 'Exception caught on xendit/checkout/invoice: ' . $e->getMessage();

            $this->getLogger()->debug('Exception caught on xendit/checkout/invoice: ' . $message);
            $this->getLogger()->debug($e->getTraceAsString());

            $this->cancelOrder($order, $e->getMessage());
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
            $this->_redirect('checkout/onepage/error', [ '_secure' => false ]);

            return;
        }

        $orderId = $order->getRealOrderId();
        $preferredMethod = $this->getRequest()->getParam('preferred_method');

        $shippingAddress = $order->getShippingAddress();
        $address = [
            'street_line1'  => $shippingAddress->getData('street') ?: 'N/A',
            'city'          => $shippingAddress->getData('city') ?: 'N/A',
            'state'         => $shippingAddress->getData('region') ?: 'N/A',
            'postal_code'   => $shippingAddress->getData('postcode') ?: 'N/A',
            'country'       => $shippingAddress->getData('country_id') ?: 'ID'
        ];

        $orderItems = $order->getAllItems();
        $items = [];
        foreach ($orderItems as $orderItem) {
            $item = [];
            $product = $orderItem->getProduct();
            $categoryIds = $product->getCategoryIds();
            $categories = [];
            foreach ($categoryIds as $categoryId) {
                $category = $this->getCategoryFactory()->create();
                $category->load($categoryId);
                $categories[] = (string) $category->getName();
            }
            $categoryName = implode(', ', $categories);
            $item['reference_id'] = $product->getId();
            $item['name'] = $product->getName();
            $item['category'] = $categoryName ?: 'Uncategorized';
            $item['price'] = $product->getPrice();
            $item['type'] = 'PRODUCT';
            $item['url'] = $product->getProductUrl();
            $item['quantity'] = (int) $orderItem->getQtyOrdered();
            $items[] = (object) $item;
        }

        $requestData = [
            'external_id'           => $this->getDataHelper()->getExternalId($orderId),
            'payer_email'           => $order->getCustomerEmail(),
            'description'           => $orderId,
            'amount'                => $order->getTotalDue(),
            'currency'              => $order->getBaseCurrencyCode(),
            'preferred_method'      => $preferredMethod,
            'client_type'           => 'INTEGRATION',
            'payment_methods'       => json_encode([strtoupper($preferredMethod)]),
            'platform_callback_url' => $this->getXenditCallbackUrl(),
            'success_redirect_url'  => $this->getDataHelper()->getSuccessUrl(),
            'failure_redirect_url'  => $this->getDataHelper()->getFailureUrl($orderId),
            'customer'              => (object) [
                'given_names'       => $order->getCustomerFirstname() ?: 'N/A',
                'surname'           => $order->getCustomerLastname() ?: 'N/A',
                'email'             => $order->getCustomerEmail() ?: 'noreply@mail.com' ,
                'mobile_number'     => $shippingAddress->getTelephone() ?: 'N/A',
                'addresses'         => [(object) $address]
            ],
            'items'                 => $items
        ];

        return $requestData;
    }

    /**
     * @param $requestData
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function createInvoice($requestData)
    {
        $invoiceUrl = $this->getDataHelper()->getCheckoutUrl() . "/payment/xendit/invoice";
        $invoiceMethod = Request::METHOD_POST;
        $invoice = '';

        try {
            if (isset($requestData['preferred_method'])) {
                $invoice = $this->getApiHelper()->request(
                    $invoiceUrl, $invoiceMethod, $requestData, false, $requestData['preferred_method']
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
        $url = $invoice['invoice_url'] . "#$preferredMethod";
        return $url;
    }

    /**
     * @param $order
     */
    private function changePendingPaymentStatus($order)
    {
        $order->setState(Order::STATE_PENDING_PAYMENT)->setStatus(Order::STATE_PENDING_PAYMENT);
        $order->addStatusHistoryComment("Pending Xendit payment.");
        $order->save();
    }

    /**
     * @param $order
     * @param $invoice
     */
    private function addInvoiceData($order, $invoice)
    {
        $payment = $order->getPayment();
        $payment->setAdditionalInformation('payment_gateway', 'xendit');
        if (isset($invoice['id'])) {
            $payment->setAdditionalInformation('xendit_invoice_id', $invoice['id']);
        }
        if (isset($invoice['expiry_date'])) {
            $payment->setAdditionalInformation('xendit_invoice_exp_date', $invoice['expiry_date']);
        }
        $order->save();
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
        $resultRedirect->setUrl($this->_url->getUrl('checkout/cart'), [ '_secure'=> false ]);
        return $resultRedirect;
    }
}
