<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Sales\Model\Order;

class Invoice extends AbstractAction
{
    public function execute()
    {
        try {
            $order = $this->getOrder();
            $apiData = $this->getApiRequestData($order);

            if ($order->getState() === Order::STATE_PROCESSING) {
                $this->changePendingPaymentStatus($order);
                $invoice = $this->createInvoice($apiData);
                $redirectUrl = $this->getXenditRedirectUrl($invoice, $apiData['preferred_method']);

                $resultRedirect = $this->getRedirectFactory()->create();
                $resultRedirect->setUrl($redirectUrl);
                return $resultRedirect;
            } else if ($order->getState() === Order::STATE_CANCELED) {
                $this->_redirect('checkout/cart');
            } else {
                $this->getLogger()->debug('Order in unrecognized state: ' . $order->getState());
                $this->_redirect('checkout/cart');
            }
            
        } catch (Exception $e) {
            $this->getLogger()->debug('Exception caught on xendit/checkout/invoice: ' . $e->getMessage());
            $this->getLogger()->debug($e->getTraceAsString());
        }
    }

    private function getApiRequestData($order)
    {
        if ($order == null) {
            $this->getLogger()->debug('Unable to get last order data from database');
            $this->_redirect('checkout/onepage/error', array( '_secure' => false ));

            return;
        }

        $orderId = $order->getRealOrderId();
        $preferredMethod = $this->getRequest()->getParam('preferred_method');

        $requestData = array(
            'success_redirect_url' => $this->getDataHelper()->getSuccessUrl(),
            'failure_redirect_url' => $this->getDataHelper()->getFailureUrl($orderId),
            'amount' => $order->getTotalDue(),
            'external_id' => $this->getDataHelper()->getExternalId($orderId),
            'description' => $orderId,
            'payer_email' => $order->getCustomerEmail(),
            'preferred_method' => $preferredMethod,
            'should_send_email' => "true"
        );

        return $requestData;
    }

    private function createInvoice($requestData)
    {
        $invoiceUrl = $this->getDataHelper()->getCheckoutUrl() . "/payment/xendit/invoice";
        $invoiceMethod = \Zend\Http\Request::METHOD_POST;

        try {
            $invoice = $this->getApiHelper()->request($invoiceUrl, $invoiceMethod, $requestData, false, $requestData['preferred_method']);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            throw new \Magento\Framework\Exception\LocalizedException(
                $e->getMessage()
            );
        }

        return $invoice;
    }

    private function getXenditRedirectUrl($invoice, $preferredMethod)
    {
        $url = $invoice['invoice_url'] . "#$preferredMethod";

        return $url;
    }

    private function changePendingPaymentStatus($order)
    {
        $order->setState(Order::STATE_PENDING_PAYMENT)->setStatus(Order::STATE_PENDING_PAYMENT);

        $order->save();
    }
}