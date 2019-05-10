<?php

namespace Xendit\M2Invoice\Controller\Checkout;

class Success extends AbstractAction
{
    public function execute()
    {
        $this->getMessageManager()->addSuccessMessage(__("Your payment with Xendit is completed"));
        $this->_redirect('checkout/onepage/success', array('_secure'=> false));
        return;
    }
}