<?php

namespace Xendit\M2Invoice\Controller\Payment;

use Magento\Multishipping\Model\Checkout\Type\Multishipping\State;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\PaymentException;
use Magento\Framework\Exception\ValidatorException;

/**
 * Class OverviewPost
 */
class OverviewPost extends \Magento\Multishipping\Controller\Checkout
{
    /**
     * @var \Magento\Framework\Data\Form\FormKey\Validator
     */
    protected $formKeyValidator;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Checkout\Api\AgreementsValidatorInterface
     */
    protected $agreementsValidator;

    protected $moduleManager;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     * @param CustomerRepositoryInterface $customerRepository
     * @param AccountManagementInterface $accountManagement
     * @param \Magento\Framework\Data\Form\FormKey\Validator $formKeyValidator
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Checkout\Api\AgreementsValidatorInterface $agreementValidator
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        CustomerRepositoryInterface $customerRepository,
        AccountManagementInterface $accountManagement,
        \Magento\Framework\Data\Form\FormKey\Validator $formKeyValidator,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Checkout\Api\AgreementsValidatorInterface $agreementValidator,
        \Magento\Framework\Module\Manager $moduleManager
    ) {
        $this->formKeyValidator = $formKeyValidator;
        $this->logger = $logger;
        $this->agreementsValidator = $agreementValidator;
        $this->moduleManager = $moduleManager;

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
            if (isset($payment['cc_number'])) {
                $paymentInstance->setCcNumber($payment['cc_number']);
            }
            if (isset($payment['cc_cid'])) {
                $paymentInstance->setCcCid($payment['cc_cid']);
            }

            $this->_getCheckout()->createOrders();
            $this->_getState()->setCompleteStep(State::STEP_OVERVIEW);

            $baseUrl = $this->_objectManager->get('\Magento\Store\Model\StoreManagerInterface')->getStore()->getBaseUrl();
            
            /**
             * Check if sprint multishipping module is enabled
             * Reason: both plugins are overriding this class
             * To ensure both can coexists, we include sprint logic
             */
            $sprintPaymentMethod = '';
            if ($this->moduleManager->isEnabled('Sprint_Sprintmultishipping')) {
                $sprintPaymentMethod = $this->_objectManager->get('Sprint\Sprintmultishipping\Helper\Data')->sprintPaymentMethod($paymentInstance->getMethod());
            }

            //XENDIT PAYMENT METHOD
            $xenditPaymentMethod = $this->_objectManager->get('Xendit\M2Invoice\Helper\Data')->xenditPaymentMethod($paymentInstance->getMethod());
            if ($xenditPaymentMethod) {
                $ids = $this->_getCheckout()->getOrderIds();

                if (empty($ids)) {
                    $this->messageManager->addError(
                        __('Failed to create order.')
                    );
                    $this->_redirect('*/*/billing');
                }

                $params  = implode("-", $ids);
                $xenditCCMethods = array('cc', 'cchosted', 'cc_installment', 'cc_subscription');
                if (in_array($xenditPaymentMethod, $xenditCCMethods)) {
                    $redirect = $baseUrl . '/xendit/checkout/ccmultishipping?order_ids=' . $params . '&preferred_method=' . $xenditPaymentMethod;
                } else {
                    $redirect = $baseUrl . '/xendit/checkout/invoicemultishipping?order_ids=' . $params.'&preferred_method='.$xenditPaymentMethod;
                }
                $this->_redirect($redirect);
            }

            //SPRINT PAYMENT METHOD
            else if ($sprintPaymentMethod) {
                $ids = $this->_getCheckout()->getOrderIds();
                $params     = implode("|", $ids);
                $redirect   = $baseUrl . $sprintPaymentMethod . '/payment/redirectmultishipping/orderIds/' . $params;
                $this->_redirect($redirect);
            }

            //OTHERS
            else
            {
                $this->_getState()->setActiveStep(State::STEP_SUCCESS);
                $this->_getCheckout()->getCheckoutSession()->clearQuote();
                $this->_getCheckout()->getCheckoutSession()->setDisplaySuccess(true);
                $this->_redirect('*/*/success');
            }
        } catch (PaymentException $e) {
            $message = $e->getMessage();
            if (!empty($message)) {
                $this->messageManager->addError($message);
            }
            $this->_redirect('*/*/billing');
        } catch (\Magento\Checkout\Exception $e) {
            $this->_objectManager->get(
                'Magento\Checkout\Helper\Data'
            )->sendPaymentFailedEmail(
                $this->_getCheckout()->getQuote(),
                $e->getMessage(),
                'multi-shipping'
            );
            $this->_getCheckout()->getCheckoutSession()->clearQuote();
            $this->messageManager->addError($e->getMessage());
            $this->_redirect('*/cart');
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->_objectManager->get(
                'Magento\Checkout\Helper\Data'
            )->sendPaymentFailedEmail(
                $this->_getCheckout()->getQuote(),
                $e->getMessage(),
                'multi-shipping'
            );
            $this->messageManager->addError($e->getMessage());
            $this->_redirect('*/*/billing');
        } catch (\Exception $e) {
            $this->logger->critical($e);
            try {
                $this->_objectManager->get(
                    'Magento\Checkout\Helper\Data'
                )->sendPaymentFailedEmail(
                    $this->_getCheckout()->getQuote(),
                    $e->getMessage(),
                    'multi-shipping'
                );
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
            }
            $logger = new \Zend\Log\Logger();
            $logger->addWriter(new \Zend\Log\Writer\Stream(BP . '/var/log/smart_checkout_error.log'));
            $logger->info('Log error checkout: ');
            $logger->info($e->getMessage());
            $this->messageManager->addError(__('Order place error'));
            $this->_redirect('*/*/billing');
        }
    }
}
