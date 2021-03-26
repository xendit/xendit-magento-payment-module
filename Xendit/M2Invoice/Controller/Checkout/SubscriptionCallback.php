<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Webapi\Exception;
use Zend\Http\Request;

/**
 * Class SubscriptionCallback
 * @package Xendit\M2Invoice\Controller\Checkout
 */
class SubscriptionCallback extends AbstractAction implements CsrfAwareActionInterface
{
    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Json|\Magento\Framework\Controller\ResultInterface
     */
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

            $isMultishipping = ($this->getRequest()->getParam('type') === 'multishipping');
            $isTokenMatched = false;

            if ($isMultishipping) {
                $orderIds = explode('-', $payload['description']);
            } else {
                $orderIds = array($payload['description']);
            }

            foreach ($orderIds as $key => $value) {
                $order = $this->getOrderFactory()->create();
                if ($isMultishipping) {
                    $order->load($value);
                } else {
                    $order->loadByIncrementId($value);
                }

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
                            'message' => 'Token mismatched. Parent token ID:' . $parentTokenId . '. Child token ID:' .$childTokenId
                        ]);

                        return $result;
                    }
                }

                $billing = $order->getBillingAddress();
                $shipping = $order->getShippingAddress();

                //create items array
                $items = array();
                $allItems = $order->getAllVisibleItems();
                foreach ($allItems as $product) {
                    array_push($items, array(
                        'product_id'    => $product->getProductId(),
                        'price'         => $product->getPrice(),
                        'qty'           => $product->getQtyOrdered()
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
                    'shipping_method'       => $order->getShippingMethod(),
                    'items'                 => $items,
                    'payment'               => $payment->getData(),
                    'transaction_id'        => $chargeId,
                    'parent_order_id'       => $order->getRealOrderId(),
                    'is_multishipping'      => $isMultishipping,
                    'masked_card_number'    => $charge['masked_card_number']
                );

                //create order
                $this->getDataHelper()->createMageOrder($orderData);
            }

            $result->setData([
                'status' => __('OK'),
                'message' => 'Callback processed successfully.'
            ]);

            return $result;
        } catch (\Exception $e) {
            $result->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);
            $result->setData([
                'status' => __('ERROR'),
                'message' => $e->getMessage()
            ]);

            return $result;
        }
    }

    /**
     * @param $invoiceId
     * @return mixed
     * @throws LocalizedException
     */
    private function getCallbackByInvoiceId($invoiceId)
    {
        $url = "https://tpi.xendit.co/payment/xendit/callbacks/invoice/" . $invoiceId;
        $method = Request::METHOD_GET;

        try {
            $response = $this->getApiHelper()->request(
                $url, $method
            );

            return $response;
        } catch (LocalizedException $e) {
            throw new LocalizedException(
                __($e->getMessage())
            );
        }
    }

    /**
     * @param $chargeId
     * @param $recurringPaymentId
     * @return mixed
     * @throws LocalizedException
     */
    private function getCreditCardCharge($chargeId, $recurringPaymentId)
    {
        $url = $this->getDataHelper()->getCheckoutUrl() . "/credit_card_charges/" . $chargeId;
        $method = Request::METHOD_GET;

        try {
            $response = $this->getApiHelper()->request(
                $url, $method, null, false, null, array(), array('recurring-payment-id' => $recurringPaymentId)
            );

            return $response;
        } catch (LocalizedException $e) {
            throw new LocalizedException(
                __($e->getMessage())
            );
        }
    }

    /**
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
