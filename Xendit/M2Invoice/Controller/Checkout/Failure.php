<?php

namespace Xendit\M2Invoice\Controller\Checkout;

class Failure extends AbstractAction {
    public function execute()
    {
        $orderIds = explode('-', $this->getRequest()->get('order_id'));

        foreach ($orderIds as $orderId) {
            $order =  $this->getOrderById($orderId);

            if ($order && $order->getId()) {
                $this->getLogger()->debug('Requested order cancellation by customer. OrderId: ' . $order->getIncrementId());
                $this->getCheckoutHelper()->cancelCurrentOrder("Xendit: Order #".($order->getId())." was cancelled by the customer.");
                
                $quoteId    = $order->getQuoteId();
                $quote      = $this->getQuoteRepository()->get($quoteId);

                $this->getCheckoutHelper()->restoreQuote($quote); //restore cart
                $this->getMessageManager()->addWarningMessage(__("Xendit payment failed. Please click on 'Update Shopping Cart'."));
            }
        }
        
        $this->_redirect('checkout/cart');
    }
}
