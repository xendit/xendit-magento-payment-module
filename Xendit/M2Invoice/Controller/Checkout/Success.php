<?php

namespace Xendit\M2Invoice\Controller\Checkout;

class Success extends AbstractAction
{
    public function execute()
    {
        $type = $this->getRequest()->getParam('type');
        $this->getMessageManager()->addSuccessMessage(__("Your payment with Xendit is completed!"));

        if ($type === 'multishipping') {
            $this->_redirect('multishipping/checkout/success');
        } else {
            $this->_redirect('checkout/onepage/success', [ '_secure'=> false ]);
        }
    }
}
