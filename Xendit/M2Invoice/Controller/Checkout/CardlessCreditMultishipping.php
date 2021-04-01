<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Framework\Controller\ResultFactory;
use Zend\Http\Request;
use Magento\Sales\Model\Order;

/**
 * Class CardlessCreditMultishipping
 * @package Xendit\M2Invoice\Controller\Checkout
 */
class CardlessCreditMultishipping extends AbstractAction
{
    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Redirect|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        try {
            $rawOrderIds        = $this->getRequest()->getParam('order_ids');
            $orderIds           = explode("-", $rawOrderIds);
            $transactionAmount  = 0;
            $orderProcessed     = false;
            $orders             = [];
            $items              = [];
            $orderIncrementIds  = '';
            $c                  = 0;

            foreach ($orderIds as $key => $value) {
                $order = $this->getOrderFactory()->create();
                $order->load($value);
                if ($c>0) {
                    $orderIncrementIds .= "-";
                }
                $orderIncrementIds .= $order->getRealOrderId();

                $orderState = $order->getState();
                if ($orderState === Order::STATE_PROCESSING && !$order->canInvoice()) {
                    $orderProcessed = true;
                    continue;
                }

                $order->setState(Order::STATE_PENDING_PAYMENT)
                      ->setStatus(Order::STATE_PENDING_PAYMENT)
                      ->addStatusHistoryComment("Pending Xendit payment.");

                // item details
                $orderItems = $order->getAllItems();
                foreach ($orderItems as $orderItem) {
                    $item = [];
                    $product = $orderItem->getProduct();
                    $item['id']         = $product->getId();
                    $item['name']       = $product->getName();
                    $item['price']      = $product->getPrice();
                    $item['type']       = $product->getTypeId(); // TODO; improve
                    $item['url']        = $product->getProductUrl();
                    $item['quantity']   = (int) $orderItem->getQtyOrdered();
                    $items[] = $item;
                }

                array_push($orders, $order);
                // save order
                $order->save();
                $transactionAmount  += (int)$order->getTotalDue();
                $c++;
            }

            if ($orderProcessed) {
                return $this->_redirect('multishipping/checkout/success');
            }
            // customer details (from last order)
            $customerDetails['first_name']  = $order->getCustomerFirstname();
            $customerDetails['last_name']   = $order->getCustomerLastname();
            $customerDetails['email']       = $order->getCustomerEmail();
            $customerDetails['phone']       = $order->getShippingAddress()->getTelephone();
            // shipping address details (from last order)
            $shippingAddress = $order->getShippingAddress();
            $shippingAddressData['first_name']   = $shippingAddress->getData('firstname');
            $shippingAddressData['last_name']    = $shippingAddress->getData('lastname');
            $shippingAddressData['address']      = $shippingAddress->getData('street');
            $shippingAddressData['city']         = $shippingAddress->getData('city');
            $shippingAddressData['postal_code']  = $shippingAddress->getData('postcode');
            $shippingAddressData['phone']        =  $shippingAddress->getData('telephone');
            $shippingAddressData['country_code'] = 'IDN'; // TODO: make dynamic

            $preferredMethod = $this->getRequest()->getParam('preferred_method');

            $args = [
                'cardless_credit_type'  => strtoupper($preferredMethod),
                'external_id'           => $this->getDataHelper()->getExternalId($rawOrderIds),
                'amount'                => round($transactionAmount),
                'payment_type'          => '3_months',
                'items'                 => $items,
                'description'           => $rawOrderIds,
                'customer_details'      => $customerDetails,
                'shipping_address'      => $shippingAddressData,
                'redirect_url'          => $this->getDataHelper()->getSuccessUrl(true),
                'callback_url'          => $this->getXenditCallbackUrl()
            ];

            // add 'payment_type'
            if ($this->getCookieManager()->getCookie('xendit_payment_type')) {
                $args['payment_type'] = $this->getCookieManager()->getCookie('xendit_payment_type');
                $this->getCookieManager()->deleteCookie('xendit_payment_type');
            }

            // send Cardless Credit Payment request
            $cardlessCreditPayment = $this->requestCardlessCreditPayment($args);

            // handle '422' error
            if ( isset($cardlessCreditPayment['error_code']) ) {
                // handle duplicate payment error
                if ($cardlessCreditPayment['error_code'] == 'DUPLICATE_PAYMENT_ERROR') {
                    $args = array_replace($args, [
                        'external_id' => $this->getDataHelper()->getExternalId($orderIncrementIds, true)
                    ]);
                    // re-send Cardless Credit Payment request
                    $cardlessCreditPayment = $this->requestCardlessCreditPayment($args);
                }
                if (isset($cardlessCreditPayment['error_code'])) {
                    $message = $this->getErrorHandler()->mapCardlessCreditErrorCode($cardlessCreditPayment['error_code']);
                    // cancel order and redirect to cart
                    return $this->processFailedPayment($orderIds, $message);
                }
            }

            if (isset($cardlessCreditPayment['redirect_url'])) {
                $redirectUrl = $cardlessCreditPayment['redirect_url'];
                $resultRedirect = $this->getRedirectFactory()->create();
                $resultRedirect->setUrl($redirectUrl);
                // redirect to Xendit Payment channel
                return $resultRedirect;
            }
        } catch (\Exception $e) {
            $message = 'Exception caught on xendit/checkout/redirect: ' . $e->getMessage();
            $this->getLogger()->info($message);
            return $this->redirectToCart("There was an error in the Xendit payment. Failure reason: Unexpected Error");
        }
    }

    /**
     * @param $failureReason
     * @return \Magento\Framework\Controller\ResultInterface
     */
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

    /**
     * @param $requestData
     * @param bool $isRetried
     * @return mixed
     * @throws \Exception
     */
    private function requestCardlessCreditPayment($requestData, $isRetried = true)
    {
        $this->logger->info(json_encode($requestData));

        $cardlessCreditUrl = $this->getDataHelper()->getCheckoutUrl() . "/payment/xendit/cardless-credit";
        $cardlessCreditMethod = Request::METHOD_POST;
        $options = [
            'timeout' => 60
        ];

        try {
            $cardlessCreditPayment = $this->getApiHelper()->request(
                $cardlessCreditUrl,
                $cardlessCreditMethod,
                $requestData,
                null,
                null,
                $options,
                [
                    'x-api-version' => '2020-02-01'
                ]
            );
            $this->logger->info(json_encode($cardlessCreditPayment));
        } catch (\Exception $e) {
            $this->logger->info(json_encode($e));

            throw $e;
        }

        return $cardlessCreditPayment;
    }

    /**
     * @param $orderIds
     * @param string $failureReason
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function processFailedPayment($orderIds, $failureReason = 'Unexpected Error with empty charge')
    {
        $this->getCheckoutHelper()->processOrdersFailedPayment($orderIds, $failureReason);

        $failureReasonInsight = $this->getDataHelper()->failureReasonInsight($failureReason);
        $this->getMessageManager()->addErrorMessage(__(
            $failureReasonInsight
        ));
        $this->_redirect('checkout/cart', ['_secure'=> false]);
    }
}
