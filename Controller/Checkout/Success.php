<?php

namespace Xendit\M2Invoice\Controller\Checkout;

/**
 * Class Success
 * @package Xendit\M2Invoice\Controller\Checkout
 */
class Success extends AbstractAction
{
    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|void
     */
    public function execute()
    {
        $type = $this->getRequest()->getParam('type');

        $this->redirectToThankYouPage($type);
    }

    /**
     * @param $type
     */
    private function redirectToThankYouPage($type)
    {
        $this->getMessageManager()->addSuccessMessage(__("Your payment is completed!"));
        if ($type === 'multishipping') {
            $this->getStateMultishipping()->setCompleteStep('multishipping_overview');

            if (!$this->getStateMultishipping()->getCompleteStep('multishipping_overview')) {
                $this->_redirect('*/*/addresses');
                return;
            }

            $this->_view->loadLayout();
            $ids = $this->getMultishippingType()->getOrderIds();
            $this->_eventManager->dispatch('multishipping_checkout_controller_success_action', ['order_ids' => $ids]);
            $this->_redirect('multishipping/checkout/success');
            $this->_view->renderLayout();
        } else {
            $this->_redirect('checkout/onepage/success', [ '_secure'=> false ]);
        }
    }
}
