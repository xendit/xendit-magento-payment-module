<?php

namespace Xendit\M2Invoice\Controller\Payment;

use Magento\Checkout\Api\AgreementsValidatorInterface;
use Magento\Checkout\Exception;
use Magento\Checkout\Helper\Data as CheckoutHelper;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\PaymentException;
use Magento\Multishipping\Controller\Checkout;
use Magento\Multishipping\Model\Checkout\Type\Multishipping\State;
use Magento\Store\Model\StoreManagerInterface;
use Xendit\M2Invoice\Helper\Data as XenditHelper;
use Xendit\M2Invoice\Logger\Logger as XenditLogger;

/**
 * Class OverviewPost
 * @package Kemana\Xendit\Controller\Payment
 */
class OverviewPost extends Checkout
{
    /**
     * @var Validator
     */
    protected $formKeyValidator;

    /**
     * @var AgreementsValidatorInterface
     */
    protected $agreementsValidator;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var XenditHelper
     */
    protected $xenditHelper;

    /**
     * @var CheckoutHelper
     */
    protected $checkoutHelper;

    /**
     * @var XenditLogger
     */
    protected $xenditLogger;

    /**
     * OverviewPost constructor.
     * @param Context $context
     * @param CustomerSession $customerSession
     * @param CustomerRepositoryInterface $customerRepository
     * @param AccountManagementInterface $accountManagement
     * @param Validator $formKeyValidator
     * @param AgreementsValidatorInterface $agreementValidator
     * @param StoreManagerInterface $storeManager
     * @param XenditHelper $xenditHelper
     * @param CheckoutHelper $checkoutHelper
     * @param XenditLogger $xenditLogger
     */
    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        CustomerRepositoryInterface $customerRepository,
        AccountManagementInterface $accountManagement,
        Validator $formKeyValidator,
        AgreementsValidatorInterface $agreementValidator,
        StoreManagerInterface $storeManager,
        XenditHelper $xenditHelper,
        CheckoutHelper $checkoutHelper,
        XenditLogger $xenditLogger
    ) {
        $this->formKeyValidator = $formKeyValidator;
        $this->agreementsValidator = $agreementValidator;
        $this->storeManager = $storeManager;
        $this->xenditHelper = $xenditHelper;
        $this->checkoutHelper = $checkoutHelper;
        $this->xenditLogger = $xenditLogger;

        parent::__construct(
            $context,
            $customerSession,
            $customerRepository,
            $accountManagement
        );
    }

    /**
     * Overview action
     *
     * @return void
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function execute()
    {
        if (!$this->formKeyValidator->validate($this->getRequest())) {
            $this->_forward('backToAddresses');
            return;
        }
        if (!$this->_validateMinimumAmount()) {
            return;
        }

        try {
            if (!$this->agreementsValidator->isValid(array_keys($this->getRequest()->getPost('agreement', [])))) {
                $this->messageManager->addError(
                    __('Please agree to all Terms and Conditions before placing the order.')
                );
                $this->_redirect('*/*/billing');
                return;
            }

            $payment = $this->getRequest()->getPost('payment');
            $paymentInstance = $this->_getCheckout()->getQuote()->getPayment();

            $this->_getCheckout()->createOrders();
            $this->_getState()->setCompleteStep(State::STEP_OVERVIEW);

            $baseUrl = $this->storeManager->getStore()->getBaseUrl();

            //XENDIT PAYMENT METHOD
            $orderIds = $this->_getCheckout()->getOrderIds();

            if (empty($orderIds)) {
                $this->messageManager->addError(
                    __('Failed to create order.')
                );
                $this->_redirect('*/*/billing');
            }

            // Check if Xendit payment method is selected
            $paymentMethod = $paymentInstance->getMethod();
            $this->xenditLogger->info('Multi-shipping payment method selected: ' . $paymentMethod);

            if (strpos($paymentMethod, 'unified') !== false) {
                $redirect = $baseUrl . '/xendit/checkout/invoicemultishipping';
                $this->_redirect($redirect);
            } else {
                // Handle non-Xendit payment methods - use default Magento multishipping behavior
                $this->xenditLogger->info('Non-Xendit payment method selected for multi-shipping, using default flow: ' . $paymentMethod);
                $this->processDefaultMultishippingSuccess();
            }
        } catch (PaymentException $e) {
            $message = $e->getMessage();
            if (!empty($message)) {
                $this->messageManager->addError($message);
            }
            $this->_redirect('*/*/billing');
        } catch (Exception $e) {
            $this->checkoutHelper->sendPaymentFailedEmail(
                $this->_getCheckout()->getQuote(),
                $e->getMessage(),
                'multi-shipping'
            );
            $this->_getCheckout()->getCheckoutSession()->clearQuote();
            $this->messageManager->addError($e->getMessage());
            $this->_redirect('*/cart');
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->checkoutHelper->sendPaymentFailedEmail(
                $this->_getCheckout()->getQuote(),
                $e->getMessage(),
                'multi-shipping'
            );
            $this->messageManager->addError($e->getMessage());
            $this->_redirect('*/*/billing');
        } catch (\Exception $e) {
            $this->xenditLogger->critical($e);
            try {
                $this->checkoutHelper->sendPaymentFailedEmail(
                    $this->_getCheckout()->getQuote(),
                    $e->getMessage(),
                    'multi-shipping'
                );
            } catch (\Exception $e) {
                $this->xenditLogger->error($e->getMessage());
            }
            $this->xenditLogger->info('Log error checkout: ');
            $this->xenditLogger->info($e->getMessage());
            $this->messageManager->addError(__('Order place error'));
            $this->_redirect('*/*/billing');
        }
    }

    /**
     * Process default Magento multishipping success flow
     * This mimics the standard Magento\Multishipping\Controller\Checkout\OverviewPost behavior
     *
     * @return void
     */
    protected function processDefaultMultishippingSuccess()
    {
        $this->_getState()->setActiveStep(State::STEP_SUCCESS);
        $this->_getCheckout()->getCheckoutSession()->clearQuote();
        $this->_getCheckout()->getCheckoutSession()->setDisplaySuccess(true);
        $this->_redirect('*/*/success');
    }
}
