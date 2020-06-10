<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Phrase;
use Magento\Sales\Model\Order;
use Xendit\M2Invoice\Enum\LogDNALevel;

class InvoiceMultishipping extends AbstractAction
{
    public function execute()
    {
        try {
            $billingEmail        = $this->getRequest()->getParam('billing_email');
            $rawOrderIds        = $this->getRequest()->getParam('order_ids');
            $orderIds           = explode("-", $rawOrderIds);

            $transactionAmount  = 0;
            $incrementIds       = [];
            
            $orders = [];
            foreach ($orderIds as $key => $value) {
                $order = $this->getOrderFactory()->create();
                $order->load($value);

                $order->setState(Order::STATE_PENDING_PAYMENT)
                    ->setStatus(Order::STATE_PENDING_PAYMENT)
                    ->addStatusHistoryComment("Pending Xendit payment.");
                    
                array_push($orders, $order);
                
                $order->save();
    
                $transactionAmount  += (int)$order->getTotalDue();
                $incrementIds[]     = $order->getIncrementId();
            }

            $externalIdSuffix = implode('-', $incrementIds);
            $preferredMethod = $this->getRequest()->getParam('preferred_method');
            $requestData = [
                'success_redirect_url' => $this->getDataHelper()->getSuccessUrl(true),
                'failure_redirect_url' => $this->getDataHelper()->getFailureUrl($rawOrderIds),
                'amount' => $transactionAmount,
                'external_id' => $this->getDataHelper()->getExternalId($externalIdSuffix),
                'description' => $rawOrderIds,
                'payer_email' => $billingEmail,
                'preferred_method' => $preferredMethod,
                'should_send_email' => "true",
                'platform_callback_url' => $this->getXenditCallbackUrl(),
                'client_type' => 'INTEGRATION'
            ];

            $invoice = $this->createInvoice($requestData);

            $this->addInvoiceData($orders, $invoice);

            $redirectUrl = $this->getXenditRedirectUrl($invoice, $preferredMethod);
            
            $resultRedirect = $this->getRedirectFactory()->create();
            $resultRedirect->setUrl($redirectUrl);
            return $resultRedirect;
        } catch (\Exception $e) {
            $message = 'Exception caught on xendit/checkout/redirect: ' . $e->getMessage();
            return $this->redirectToCart("There was an error in the Xendit payment. Failure reason: Unexpected Error");
        }
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

    private function addInvoiceData($orders, $invoice)
    {
        foreach ($orders as $key => $order) {
            $payment = $order->getPayment();
            $payment->setAdditionalInformation('payment_gateway', 'xendit');
            $payment->setAdditionalInformation('xendit_invoice_id', $invoice['id']);
            $payment->setAdditionalInformation('xendit_invoice_exp_date', $invoice['expiry_date']);
            
            $order->save();
        }
    }
    
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
