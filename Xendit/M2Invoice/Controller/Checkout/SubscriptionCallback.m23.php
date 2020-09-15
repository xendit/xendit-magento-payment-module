<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;

class SubscriptionCallback extends AbstractAction implements CsrfAwareActionInterface
{
    public function execute()
    {
        $result = $this->getJsonResultFactory()->create();

        try {
            $post = $this->getRequest()->getContent();
            $payload = json_decode($post, true);

            $invoiceId = $payload['id'];
            $chargeId = $payload['credit_card_charge_id'];

            //verify callback
            $callback = $this->getCallbackByInvoiceId($invoiceId);
            if (isset($callback['error_code']) || !isset($callback['status'])) {
                $result->setData([
                    'status' => __('ERROR'),
                    'message' => (!empty($callback['error_code']) ?: 'Callback not found')
                ]);
    
                return $result;
            } else if ($callback['status'] == 'COMPLETED') {
                $result->setData([
                    'status' => __('ERROR'),
                    'message' => 'Callback already processed'
                ]);
    
                return $result;
            }

            //verify charge
            $charge = $this->getCreditCardCharge($chargeId, $payload['recurring_payment_id']); //child charge
            if ($charge['status'] != 'CAPTURED' && $charge['status'] != 'SETTLED') {
                $result->setData([
                    'status' => __('ERROR'),
                    'message' => 'Charge is ' . $charge['status']
                ]);
    
                return $result;
            }
            
            if (empty($charge['token_id'])) {
                $result->setData([
                    'status' => __('ERROR'),
                    'message' => 'Token ID not found'
                ]);
    
                return $result;
            }
            $childTokenId = $charge['token_id'];

            $orderIds = explode('-', $payload['description']); //parent order id(s)
            $isTokenMatched = false;

            foreach ($orderIds as $key => $value) {
                $order = $this->getOrderFactory()->create();
                $order->load($value);
                $payment = $order->getPayment();

                //match token id of parent & child's order just once
                if (!$isTokenMatched) {
                    $parentTokenId = $payment->getAdditionalInformation('token_id');
                    if ($parentTokenId == $childTokenId) {
                        $isTokenMatched = true;
                    }
                    else {
                        $result->setData([
                            'status' => __('ERROR'),
                            'message' => 'Token mismatched'
                        ]);
            
                        return $result;
                    }
                }

                $billing = $order->getBillingAddress();
                $shipping = $order->getShippingAddress();

                //create items array
                $items = array();
                $allItems = $order->getAllItems();
                foreach ($allItems as $product) {
                    array_push($items, array(
                        'product_id'    => $product->getProductId(), 
                        'qty'           => $product->getQtyOrdered(),
                        'price'         => $product->getPrice()
                    ));
                }

                $orderData = array(
                    'currency_id'       => $order->getBaseCurrencyCode(),
                    'email'             => $order->getCustomerEmail(),
                    'billing_address'   => array(
                        'firstname'             => $order->getCustomerFirstname(),
                        'lastname'              => $order->getCustomerLastname(),
                        'street'                => $billing->getStreetLine(1),
                        'city'                  => $billing->getCity(),
                        'country_id'            => $billing->getCountryId(),
                        'region'                => $billing->getRegion(),
                        'postcode'              => $billing->getPostcode(),
                        'telephone'             => $billing->getTelephone(),
                        'fax'                   => $billing->getFax(),
                        'save_in_address_book'  => 0
                    ),
                    'shipping_address'  => array(
                        'firstname'             => $order->getCustomerFirstname(),
                        'lastname'              => $order->getCustomerLastname(),
                        'street'                => $shipping->getStreetLine(1),
                        'city'                  => $shipping->getCity(),
                        'country_id'            => $shipping->getCountryId(),
                        'region'                => $shipping->getRegion(),
                        'postcode'              => $shipping->getPostcode(),
                        'telephone'             => $shipping->getTelephone(),
                        'fax'                   => $shipping->getFax(),
                        'save_in_address_book'  => 0
                    ),
                    'shipping_method'   => $order->getShippingMethod(),
                    'items'             => $items,
                    'payment'           => $payment->getData(),
                    'transaction_id'    => $chargeId,
                    'parent_order_id'   => $order->getRealOrderId(),
                    'is_multishipping'  => (count($orderIds) > 1 ? true : false)
                );

                //create order
                $this->getObjectManager()->get('Xendit\M2Invoice\Helper\Data')->createMageOrder($orderData);
            }

            $result->setData([
                'status' => __('OK'),
                'message' => 'Callback processed successfully.'
            ]);

            return $result;
        } catch (\Exception $e) {
            $result->setHttpResponseCode(\Magento\Framework\Webapi\Exception::HTTP_BAD_REQUEST);
            $result->setData([
                'status' => __('ERROR'),
                'message' => $e->getMessage()
            ]);

            return $result;
        }
    }

    private function getCallbackByInvoiceId($invoiceId)
    {
        $url = $this->getDataHelper()->getCheckoutUrl() . "/payment/xendit/callbacks/invoice/" . $invoiceId;
        $method = \Zend\Http\Request::METHOD_GET;

        try {
            $response = $this->getApiHelper()->request(
                $url, $method
            );

            return $response;
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            throw new LocalizedException(
                __($e->getMessage())
            );
        }
    }

    private function getCreditCardCharge($chargeId, $recurringPaymentId)
    {
        $url = $this->getDataHelper()->getCheckoutUrl() . "/payment/xendit/credit-card/charges/" . $chargeId;
        $method = \Zend\Http\Request::METHOD_GET;

        try {
            $response = $this->getApiHelper()->request(
                $url, $method, null, false, null, array(), array('recurring-payment-id' => $recurringPaymentId)
            );

            return $response;
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            throw new LocalizedException(
                __($e->getMessage())
            );
        }
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
