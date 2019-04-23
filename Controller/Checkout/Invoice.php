<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Sales\Model\Order;

class Invoice extends AbstractAction
{
    public function execute()
    {
        try {
            $order = $this->getOrder();
            echo '
                <p>Yea</p>
            ';
        } catch (Exception $e) {
            $this->getLogger()->debug('Exception caught on xendit/checkout/invoice: ' . $e->getMessage());
            $this->getLogger()->debug($e->getTraceAsString());
        }
    }
}