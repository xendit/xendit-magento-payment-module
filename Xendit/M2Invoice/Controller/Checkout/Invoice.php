<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Sales\Model\Order;

class Invoice extends AbstractAction
{
    public function execute()
    {
        try {
            $order = $this->getOrder();
            $requestData = $this->getRequestData($order);

            if ($order->getState() === Order::STATE_PROCESSING) {
                $this->postToCheckout($requestData);
            } else if ($order->getState() === Order::STATE_CANCELED) {
                $this->_redirect('checkout/cart');
            } else {
                $this->getLogger()->debug('Order in unrecognized state: ' . $order->getState());
                $this->_redirect('checkout/cart');
            }
            
        } catch (Exception $e) {
            $this->getLogger()->debug('Exception caught on xendit/checkout/invoice: ' . $e->getMessage());
            $this->getLogger()->debug($e->getTraceAsString());
        }
    }

    private function getRequestData($order)
    {
        if ($order == null) {
            $this->getLogger()->debug('Unable to get last order data from database');
            $this->_redirect('checkout/onepage/error', array( '_secure' => false ));

            return;
        }

        $orderId = $order->getRealOrderId();
        $preferredMethod = $this->getRequest()->getParam('preferred_method');

        $requestData = array(
            'x_url_success' => $this->getDataHelper()->getSuccessUrl(),
            'x_url_failure' => $this->getDataHelper()->getFailureUrl($orderId),
            'x_amount' => $order->getTotalDue(),
            'x_external_id' => $this->getDataHelper()->getExternalId($orderId),
            'x_preferred_method' => $preferredMethod,
            'x_validation_token' => $this->getDataHelper()->getValidationKey()
        );

        $signature = $this->getCryptoHelper()->generateSignature($requestData, $this->getDataHelper()->getApiKey());
        $requestData['x_signature'] = $signature;

        return $requestData;
    }

    private function postToCheckout($requestData)
    {
        $checkoutUrl = $this->getDataHelper()->getCheckoutUrl() . '/payment/xendit/invoice/public-generate';

        echo
        "
        <html>
            <body>
                <form id='xencheckout' action='$checkoutUrl' method='post'>";
                foreach ($requestData as $k => $v) {
                    echo "<input type='hidden' id='$k' value='" . htmlspecialchars($v, ENT_QUOTES) . "'/>";
                }
                echo
                "</form>
            </body>";
            echo
            "<script>
                var form = document.getElementById('xencheckout');
            </script>
        </html>
        ";
    }
}