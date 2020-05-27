<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Model\Order;
use Xendit\M2Invoice\Enum\LogDNALevel;

class InvoiceMultishipping extends AbstractAction
{
    public function execute()
    {
        try {
            $rawOrderIds        = $this->getRequest()->getParam('order_ids');
            $orderIds           = explode("|", $rawOrderIds);

            $transactionAmount  = 0;
            $incrementIds       = [];

            foreach ($orderIds as $key => $value) {
                $order              = $this->getOrderFactory()->create();
                $order->load($value);
                $payment            = $order->getQuoteId();

                echo $payment . '</br>';

                $quote = $this->getQuoteRepository()->get($payment);

                var_dump($quote->getPayment()->getAdditionalInformation());
    
                $transactionAmount  += (int)$order->getTotalDue();
                $incrementIds[]     = $order->getIncrementId();
    
                $id = $value;
            }

            echo 'something ' . $transactionAmount;
            exit;
        } catch (\Exception $e) {
            echo 'something ' . $e->getMessage();
            exit;
            $message = 'Exception caught on xendit/checkout/redirect: ' . $e->getMessage();
            return $this->redirectToCart("There was an error in the Xendit payment. Failure reason: Unexpected Error");
        }
    }

    private function redirectToCart($failureReason)
    {
        $failureReasonInsight = $this->getDataHelper()->failureReasonInsight($failureReason);
        $this->getMessageManager()->addErrorMessage(__(
            $failureReasonInsight
        ));
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($this->_url->getUrl('checkout/cart'), [ '_secure'=> false ]);
        return $resultRedirect;
    }
}
