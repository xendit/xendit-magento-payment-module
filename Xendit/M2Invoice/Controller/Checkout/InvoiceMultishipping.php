<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Phrase;
use Magento\Sales\Model\Order;
use Magento\Framework\Exception\LocalizedException;
use Zend\Http\Request;

/**
 * Class InvoiceMultishipping
 * @package Xendit\M2Invoice\Controller\Checkout
 */
class InvoiceMultishipping extends AbstractAction
{
    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Redirect|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        try {
            $rawOrderIds        = $this->getRequest()->getParam('order_ids');
            $orderIds           = explode("-", $rawOrderIds);

            $transactionAmount  = 0;
            $orderProcessed     = false;
            $orders             = [];

            $orderIncrementIds = '';

            $c = 0;
            foreach ($orderIds as $key => $value) {
                $order = $this->getOrderFactory()->create();
                $order->load($value);
                if ($c>0) {
                    $orderIncrementIds .= "-";
                }
                $orderIncrementIds .= $order->getRealOrderId();

                $orderState = $order->getState();
                if ($orderState === Order::STATE_PROCESSING && !$order->canInvoice()) {
                    $orderProcessed = true;
                    continue;
                }

                $order->setState(Order::STATE_PENDING_PAYMENT)
                      ->setStatus(Order::STATE_PENDING_PAYMENT)
                      ->addStatusHistoryComment("Pending Xendit payment.");
                    
                array_push($orders, $order);
                
                $order->save();
    
                $transactionAmount  += (int)$order->getTotalDue();
                $billingEmail = $order->getCustomerEmail();
                $currency = $order->getBaseCurrencyCode();
                $c++;
            }

            if ($orderProcessed) {
                return $this->_redirect('multishipping/checkout/success');
            }

            $preferredMethod = $this->getRequest()->getParam('preferred_method');
            if ($preferredMethod == 'cc') {
                $preferredMethod = 'CREDIT_CARD';
            }

            $requestData = [
                'external_id'           => $this->getDataHelper()->getExternalId($rawOrderIds),
                'payer_email'           => $billingEmail,
                'description'           => $rawOrderIds,
                'amount'                => $transactionAmount,
                'currency'              => $currency,
                'preferred_method'      => $preferredMethod,
                'should_send_email'     => $this->getDataHelper()->getSendInvoiceEmail() ? "true" : "false",
                'client_type'           => 'INTEGRATION',
                'payment_methods'       => json_encode([strtoupper($preferredMethod)]),
                'platform_callback_url' => $this->getXenditCallbackUrl(),
                'success_redirect_url'  => $this->getDataHelper()->getSuccessUrl(true),
                'failure_redirect_url'  => $this->getDataHelper()->getFailureUrl($orderIncrementIds, true)
            ];

            $invoice = $this->createInvoice($requestData);

            if (isset($invoice['error_code'])) {
                $message = $this->getErrorHandler()->mapInvoiceErrorCode($invoice['error_code']);
                // cancel order and redirect to cart
                return $this->processFailedPayment($orderIds, $message);
            }

            $this->addInvoiceData($orders, $invoice);

            $redirectUrl = $this->getXenditRedirectUrl($invoice, $preferredMethod);
            $resultRedirect = $this->getRedirectFactory()->create();
            $resultRedirect->setUrl($redirectUrl);
            return $resultRedirect;

        } catch (\Exception $e) {
            $message = 'Exception caught on xendit/checkout/redirect: ' . $e->getMessage();
            $this->getLogger()->info($message);
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
        $invoiceUrl = $this->getDataHelper()->getCheckoutUrl() . "/payment/xendit/invoice";
        $invoiceMethod = Request::METHOD_POST;

        try {
            if (isset($requestData['preferred_method'])) {
                $invoice = $this->getApiHelper()->request(
                    $invoiceUrl, $invoiceMethod, $requestData, false, $requestData['preferred_method']
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
        $url = (isset($invoice['invoice_url']))? $invoice['invoice_url'] . "#$preferredMethod" : '';
        return $url;
    }

    /**
     * @param $orders
     * @param $invoice
     */
    private function addInvoiceData($orders, $invoice)
    {
        foreach ($orders as $key => $order) {
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

    /**
     * @param $orderIds
     * @param string $failureReason
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function processFailedPayment($orderIds, $failureReason = 'Unexpected Error with empty charge')
    {
        $this->getCheckoutHelper()->processOrdersFailedPayment($orderIds, $failureReason);

        $failureReasonInsight = $this->getDataHelper()->failureReasonInsight($failureReason);
        $this->getMessageManager()->addErrorMessage(__(
            $failureReasonInsight
        ));
        $this->_redirect('checkout/cart', ['_secure'=> false]);
    }
}
