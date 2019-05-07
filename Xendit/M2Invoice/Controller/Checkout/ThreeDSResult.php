<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Sales\Model\Order;

class ThreeDSResult extends AbstractAction
{
    public function execute()
    {
        $orderId = $this->getRequest()->get('order_id');
        $hosted3DSId = $this->getRequest()->get('hosted_3ds_id');

        $order = $this->getOrderById($orderId);

        if (!is_object($order)) {
            return;
        }

        if ($order->getState() !== Order::STATE_PENDING_PAYMENT) {
            return;
        }

        $requestUrl = $this->getDataHelper()->getCheckoutUrl() . "/payment/xendit/credit-card/hosted-3ds/$hosted3DSId";
        $method = \Zend\Http\Request::METHOD_GET;
        
        $hosted3DS = $this->getApiHelper()->request($requestUrl, $method, null, true);
        
        $test = print_r($hosted3DS, true);
        echo "<p>testing ga sii $requestUrl $method $test</p>";
        return;
    }
}