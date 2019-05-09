<?php

namespace Xendit\M2Invoice\Controller\Checkout;

class Success extends AbstractAction
{
    public function execute()
    {
        $this->_redirect('checkout/onepage/success', array('_secure'=> false));
        return;
    }
}