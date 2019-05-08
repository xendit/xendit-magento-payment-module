<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Sales\Model\Order;

class Redirect extends AbstractAction
{
    public function execute()
    {
        try {
            $order = $this->getOrder();
            $payment = $order->getPayment();
            $redirectUrl = $payment->getAdditionalInformation('xendit_redirect_url');

            $resultRedirect = $this->getRedirectFactory()->create();
            $resultRedirect->setUrl($redirectUrl);
            return $resultRedirect;
        } catch (Exception $e) {
            $this->getLogger()->debug('Exception caught on xendit/checkout/redirect: ' . $e->getMessage());
            $this->getLogger()->debug($e->getTraceAsString());
        }
    }
}