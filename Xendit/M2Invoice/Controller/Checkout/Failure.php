<?php

namespace Xendit\M2Invoice\Controller\Checkout;

class Failure extends AbstractAction {
    public function execute()
    {
        $orderIds = explode('-', $this->getRequest()->get('order_id'));
        $type = $this->getRequest()->get('type');

        if ($type == 'multishipping') {
            foreach ($orderIds as $orderId) {
                $order = $this->getOrderFactory()->create();
                $order ->load($orderId);
    
                $this->cancelOrder($order);
            }
        } else { //onepage
            $order = $this->getOrderById($this->getRequest()->get('order_id'));

            $this->cancelOrder($order);
        }

        $this->getMessageManager()->addWarningMessage(__("Xendit payment failed. Please click on 'Update Shopping Cart'."));
        $this->_redirect('checkout/cart');
    }

    private function cancelOrder($order) {
        if ($order) {
            if ($order->canInvoice()) {
                $this->getLogger()->debug('Requested order cancelled by customer. OrderId: ' . $order->getIncrementId());
                $this->cancelOrder($order, "customer cancelled the payment.");
    
                $quoteId    = $order->getQuoteId();
                $quote      = $this->getQuoteRepository()->get($quoteId);
    
                $this->getCheckoutHelper()->restoreQuote($quote); //restore cart
            }
        }
    }
}