<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Sales\Model\Order;
use Magento\Framework\Phrase;
use Xendit\M2Invoice\Enum\LogDNA_Level;
use Xendit\M2Invoice\Enum\LogDNALevel;

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

            $this->getLogDNA()->log(LogDNALevel::ERROR, $message, $apiData);

            $this->cancelOrder($order, $e->getMessage());
            $this->getMessageManager()->addErrorMessage(__(
                "There was an error in the Xendit payment. Failure reason: Unexpected Error"
            ));
            $this->_redirect('checkout/cart', [ '_secure'=> false ]);
        }
    }

    private function getApiRequestData($order)
    {
        if ($order == null) {
            $this->getLogger()->debug('Unable to get last order data from database');
            $this->_redirect('checkout/onepage/error', [ '_secure' => false ]);

            return;
        }

        $orderId = $order->getEntityId();
        $preferredMethod = $this->getRequest()->getParam('preferred_method');

        $requestData = [
            'success_redirect_url' => $this->getDataHelper()->getSuccessUrl(),
            'failure_redirect_url' => $this->getDataHelper()->getFailureUrl($orderId),
            'amount' => $order->getTotalDue(),
            'external_id' => $this->getDataHelper()->getExternalId($orderId),
            'description' => $orderId,
            'payer_email' => $order->getCustomerEmail(),
            'preferred_method' => $preferredMethod,
            'should_send_email' => "true",
            'platform_callback_url' => $this->getXenditCallbackUrl(),
            'client_type' => 'INTEGRATION'
        ];

        return $requestData;
    }

    private function createInvoice($requestData)
    {
        $invoiceUrl = $this->getDataHelper()->getCheckoutUrl() . "/payment/xendit/invoice";
        $invoiceMethod = \Zend\Http\Request::METHOD_POST;

        try {
            $invoice = $this->getApiHelper()->request(
                $invoiceUrl, $invoiceMethod, $requestData, false, $requestData['preferred_method']
            );
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            throw new \Magento\Framework\Exception\LocalizedException(
                new Phrase($e->getMessage())
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

    private function addInvoiceData($order, $invoice)
    {
        $payment = $order->getPayment();
        $payment->setAdditionalInformation('payment_gateway', 'xendit');
        $payment->setAdditionalInformation('xendit_invoice_id', $invoice['id']);
        $payment->setAdditionalInformation('xendit_invoice_exp_date', $invoice['expiry_date']);

        $order->save();
    }
}
