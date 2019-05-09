<?php

namespace Xendit\M2Invoice\Controller\Checkout;

class Failure extends AbstractAction {
    public function execute()
    {
        $orderId = $this->getRequest()->get('order_id');
        $order =  $this->getOrderById($orderId);

        if ($order && $order->getId()) {
            $this->getLogger()->debug('Requested order cancellation by customer. OrderId: ' . $order->getIncrementId());
            $this->getCheckoutHelper()->cancelCurrentOrder("Xendit: ".($order->getId())." was cancelled by the customer.");
            $this->getCheckoutHelper()->restoreQuote(); //restore cart
            $this->getMessageManager()->addWarningMessage(__("Xendit payment failed. Please click on 'Update Shopping Cart'."));
        }
        $this->_redirect('checkout/cart');
    }
}