<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;

/**
 * Class Success
 * @package Xendit\M2Invoice\Controller\Checkout
 */
class Success extends AbstractAction
{
    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute()
    {
        $type = $this->getRequest()->getParam('type');
        $paymentType = $this->getRequest()->getParam('payment_type');
        $ewalletType = $this->getRequest()->getParam('ewallet_type');
        $externalId = $this->getRequest()->getParam('external_id');

        if (strtoupper($paymentType) === 'EWALLET') {
            /**
             * For eWallet payment (DANA/LINKAJA), there is only `redirect_url` parameter on API,
             * meaning all eWallet transaction will eventually ended up in this endpoint, regardless
             * of the transaction result (failed or successful).
             *
             * The logic inside processEwalletRedirect() makes sure that ewallet transaction will be
             * redirected to the appropriate page:
             * - Thank you page -> if eWallet transaction is successful
             * - Cart page -> if eWallet transaction is failed
             */
            $this->processEwalletRedirect($type, $externalId, $ewalletType);
        }
        else{
            $this->redirectToThankYouPage($type);
        }
    }

    /**
     * @param $type
     * @param $externalId
     * @param $ewalletType
     * @return \Magento\Framework\App\ResponseInterface|void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function processEwalletRedirect($type, $externalId, $ewalletType)
    {
        if (empty($externalId) || empty($ewalletType)) {
            $this->getMessageManager()->addWarningMessage(__("Xendit payment failed. Please retry your order."));
            $this->_redirect('checkout/cart');
        }

        $ewalletStatus = $this->getEwalletStatus($ewalletType, $externalId);

        if ($ewalletStatus === 'COMPLETED') {
            $this->redirectToThankYouPage($type);
        } else {
            $this->handleEwalletPaymentFailed($type);
        }
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

    /**
     * @param $type
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function handleEwalletPaymentFailed($type)
    {
        if ($type == 'multishipping') {
            $orderIds = explode('-', $this->getRequest()->get('order_id'));
            foreach ($orderIds as $orderId) {
                $order = $this->getOrderFactory()->create();
                $order ->load($orderId);

                if ($order) {
                    $this->getLogger()->debug('Requested order payment is failed. OrderId: ' . $order->getIncrementId());
                    $this->cancelOrder($order, "customer failed the payment.");

                    $quoteId    = $order->getQuoteId();
                    $quote      = $this->getQuoteRepository()->get($quoteId);

                    $this->getCheckoutHelper()->restoreQuote($quote); //restore cart
                }
            }
        } else { //onepage
            $order = $this->getOrderById($this->getRequest()->get('order_id'));

            if ($order) {
                $this->getLogger()->debug('Requested order payment is failed. OrderId: ' . $order->getIncrementId());
                $this->cancelOrder($order, "customer failed the payment.");

                $quoteId    = $order->getQuoteId();
                $quote      = $this->getQuoteRepository()->get($quoteId);

                $this->getCheckoutHelper()->restoreQuote($quote); //restore cart
            }
        }

        $this->getMessageManager()->addWarningMessage(__("Xendit payment failed. Please click on 'Update Shopping Cart'."));
        $this->_redirect('checkout/cart');
    }

    /**
     * @param $ewalletType
     * @param $externalId
     * @return string
     * @throws LocalizedException
     */
    private function getEwalletStatus($ewalletType, $externalId)
    {
        $ewalletUrl = $this->getDataHelper()->getCheckoutUrl() . "/ewallets?ewallet_type=".strtoupper($ewalletType)."&external_id=".$externalId;
        $ewalletMethod = \Zend\Http\Request::METHOD_GET;

        try {
            $response = $this->getApiHelper()->request($ewalletUrl, $ewalletMethod);
        } catch (LocalizedException $e) {
            throw new LocalizedException(
                new Phrase($e->getMessage())
            );
        }

        $statusList = array("COMPLETED", "PAID", "SUCCESS_COMPLETED"); //OVO, DANA, LINKAJA
        if (in_array($response['status'], $statusList)) {
            return "COMPLETED";
        }

        return $response['status'];
    }
}
