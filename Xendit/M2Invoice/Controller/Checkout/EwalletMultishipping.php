<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Phrase;
use Magento\Sales\Model\Order;
use Magento\Framework\Exception\LocalizedException;
use Zend\Http\Request;

/**
 * Class EwalletMultishipping
 * @package Xendit\M2Invoice\Controller\Checkout
 */
class EwalletMultishipping extends AbstractAction
{
    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        try {
            $rawOrderIds        = $this->getRequest()->getParam('order_ids');
            $orderIds           = explode("-", $rawOrderIds);

            $transactionAmount  = 0;
            $orderProcessed     = false;
            $orders             = [];

            $orderIncrementIds = '';
            $c = 0;

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

                array_push($orders, $order);

                $order->save();

                $transactionAmount  += (int)$order->getTotalDue();
                $c++;
            }

            if ($orderProcessed) {
                return $this->_redirect('multishipping/checkout/success');
            }

            $preferredMethod = $this->getRequest()->getParam('preferred_method');

            $items = [];
            $quoteId = $order->getQuoteId();
            $quote = $this->getQuoteRepository()->get($quoteId);
            foreach ($quote->getAllItems() as $item) {
                $items[] = [
                    'id'        => $item->getId(),
                    'name'      => $item->getName(),
                    'price'     => $item->getPrice(),
                    'quantity'  => $item->getQty()
                ];
            }

            $args = [
                'external_id'           => $this->getDataHelper()->getExternalId($rawOrderIds),
                'amount'                => round($transactionAmount),
                'items'                 => $items,
                'description'           => $rawOrderIds,
                'platform_callback_url' => $this->getXenditCallbackUrl(),
                'callback_url'          => $this->getXenditCallbackUrl(),
                'redirect_url'          => $this->getDataHelper()->getSuccessUrl(true) . '&external_id=' . $this->getDataHelper()->getExternalId($rawOrderIds) . '&ewallet_type=' . strtoupper($preferredMethod) . '&payment_type=EWALLET&order_id=' . $rawOrderIds,
                'ewallet_type'          => strtoupper($preferredMethod),
            ];

            if ($this->getCookieManager()->getCookie('xendit_phone_number')) {
                $args['phone'] = $this->getCookieManager()->getCookie('xendit_phone_number');
                $this->getCookieManager()->deleteCookie('xendit_phone_number');
            }

            $ewalletPayment = $this->requestEwalletPayment($args);

            if (isset($ewalletPayment['error_code'])) {
                $message = $this->getErrorHandler()->mapEwalletsErrorCode($ewalletPayment['error_code']);
                return $this->processFailedPayment($orderIds, $message);
            }

            if (isset($ewalletPayment['checkout_url'])) {
                $redirectUrl = $ewalletPayment['checkout_url'];
                $resultRedirect = $this->getRedirectFactory()->create();
                $resultRedirect->setUrl($redirectUrl);
                return $resultRedirect;
            }

            if ($preferredMethod == 'ovo') {
                $redirectUrl = $this->getDataHelper()->getSuccessUrl(true);
                $isSuccessful = false;
                $loopCondition = true;
                $startTime = time();
                while ($loopCondition && (time() - $startTime < 70)) {
                    if ($order->getState() !== Order::STATE_PENDING_PAYMENT) {
                        $loopCondition = false;
                        $isSuccessful = $order->getState() === Order::STATE_PROCESSING;
                    }
                    sleep(1);
                }

                if ($order->getState() === Order::STATE_PENDING_PAYMENT) {
                    $ewalletStatus = (isset($ewalletPayment['external_id'])) ? $this->getEwalletStatus('OVO', $ewalletPayment['external_id']) : '';

                    if ($ewalletStatus === 'COMPLETED') {
                        $isSuccessful = true;
                    }
                }

                if ($isSuccessful) {
                    $resultRedirect = $this->getRedirectFactory()->create();
                    $resultRedirect->setUrl($redirectUrl);
                    return $resultRedirect;
                } else {
                    $payment = $order->getPayment();
                    $failureCode = $payment->getAdditionalInformation('xendit_ewallet_failure_code');

                    if ($failureCode === null) {
                        $failureCode = 'Payment is ' . $ewalletStatus;
                    }

                    $this->getCheckoutHelper()->restoreQuote();
                    return $this->redirectToCart($failureCode);
                }
            }
        } catch (\Exception $e) {
            $message = 'Exception caught on xendit/checkout/redirect: ' . $e->getMessage();
            $this->getLogger()->info($message);
            return $this->redirectToCart($message);
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
    private function requestEwalletPayment($requestData, $isRetried = true)
    {
        $this->logger->info(json_encode($requestData));

        $ewalletUrl = $this->getDataHelper()->getCheckoutUrl() . "/payment/xendit/ewallets";
        $ewalletMethod = Request::METHOD_POST;
        $options = [
            'timeout' => 60
        ];

        try {
            $ewalletPayment = $this->getApiHelper()->request(
                $ewalletUrl,
                $ewalletMethod,
                $requestData,
                null,
                null,
                $options,
                [
                    'x-api-version' => '2020-02-01'
                ]
            );
            $this->logger->info(json_encode($ewalletPayment));
        } catch (\Exception $e) {
            $this->logger->info(json_encode($e));

            throw $e;
        }

        return $ewalletPayment;
    }

    /**
     * @param $ewalletType
     * @param $externalId
     * @return string
     * @throws \Exception
     */
    private function getEwalletStatus($ewalletType, $externalId)
    {
        $ewalletUrl = $this->getDataHelper()->getCheckoutUrl() . "/payment/xendit/ewallets?ewallet_type=" . $ewalletType . "&external_id=" . $externalId;
        $ewalletMethod = Request::METHOD_GET;

        try {
            $response = $this->getApiHelper()->request($ewalletUrl, $ewalletMethod);
        } catch (LocalizedException $e) {
            throw new LocalizedException(
                new Phrase($e->getMessage())
            );
        }

        $statusList = ["COMPLETED", "PAID", "SUCCESS_COMPLETED"]; //OVO, DANA, LINKAJA
        if (in_array($response['status'], $statusList)) {
            return "COMPLETED";
        }

        return $response['status'];
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
