<?php

namespace Xendit\M2Invoice\Helper;

use Magento\Catalog\Model\Product;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DataObject;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteManagement;
use Magento\Store\Model\StoreManagerInterface;
use Xendit\M2Invoice\Model\Payment\M2Invoice;

class Data extends AbstractHelper
{
    private $objectManager;

    private $storeManager;

    private $m2Invoice;

    private $fileSystem;

    private $product;

    private $customerRepository;

    private $customerFactory;

    private $quote;

    private $quoteManagement;

    public function __construct(
        ObjectManagerInterface $objectManager,
        Context $context,
        StoreManagerInterface $storeManager,
        M2Invoice $m2Invoice,
        File $fileSystem,
        Product $product,
        CustomerRepositoryInterface $customerRepository,
        CustomerFactory $customerFactory,
        QuoteFactory $quote,
        QuoteManagement $quoteManagement
    ) {
        $this->objectManager = $objectManager;
        $this->storeManager = $storeManager;
        $this->m2Invoice = $m2Invoice;
        $this->fileSystem = $fileSystem;
        $this->product = $product;
        $this->customerRepository = $customerRepository;
        $this->customerFactory = $customerFactory;
        $this->quote = $quote;
        $this->quoteManagement = $quoteManagement;

        parent::__construct($context);
    }

    protected function getStoreManager()
    {
        return $this->storeManager;
    }

    public function getCheckoutUrl()
    {
        return $this->m2Invoice->getConfigData('xendit_url');
    }

    public function getUiUrl()
    {
        return $this->m2Invoice->getUiUrl();
    }

    public function getSuccessUrl($isMultishipping = false)
    {
        $baseUrl = $this->getStoreManager()->getStore()->getBaseUrl() . 'xendit/checkout/success';
        if ($isMultishipping) {
            $baseUrl .= '?type=multishipping';
        }

        return $baseUrl;
    }

    public function getFailureUrl($orderId, $isMultishipping = false)
    {
        $baseUrl = $this->getStoreManager()->getStore()->getBaseUrl() . "xendit/checkout/failure?order_id=$orderId";
        if ($isMultishipping) {
            $baseUrl .= '&type=multishipping';
        }
        return $baseUrl;
    }

    public function getThreeDSResultUrl($orderId, $isMultishipping = false)
    {
        $baseUrl = $this->getStoreManager()->getStore()->getBaseUrl() . "xendit/checkout/threedsresult?order_id=$orderId";
        if ($isMultishipping) {
            $baseUrl .= "&type=multishipping";
        }
        return $baseUrl;
    }

    public function getExternalId($orderId, $duplicate = false)
    {
        $defaultExtId = $this->getExternalIdPrefix() . "-$orderId";

        if ($duplicate) {
            return uniqid() . "-" . $defaultExtId;
        }

        return $defaultExtId;
    }

    public function getExternalIdPrefix()
    {
        return $this->m2Invoice->getConfigData('external_id_prefix') . "-" . $this->getStoreName();
    }

    public function getStoreName()
    {
        return substr(preg_replace("/[^a-z0-9]/mi", "", $this->getStoreManager()->getStore()->getName()), 0, 20);
    }

    public function getApiKey()
    {
        return $this->m2Invoice->getApiKey();
    }

    public function getPublicApiKey()
    {
        return $this->m2Invoice->getPublicApiKey();
    }

    public function getSubscriptionInterval()
    {
        return $this->m2Invoice->getSubscriptionInterval() ?: 'MONTH';
    }

    public function getSubscriptionIntervalCount()
    {
        return $this->m2Invoice->getSubscriptionIntervalCount() ?: 1;
    }

    public function getEnvironment()
    {
        return $this->m2Invoice->getEnvironment();
    }

    public function getCardPaymentType()
    {
        return $this->m2Invoice->getCardPaymentType();
    }

    public function getAllowedMethod()
    {
        return $this->m2Invoice->getAllowedMethod();
    }

    public function getChosenMethods()
    {
        return $this->m2Invoice->getChosenMethods();
    }

    public function getEnabledPromo()
    {
        return $this->m2Invoice->getEnabledPromo();
    }

    public function getIsActive()
    {
        return $this->m2Invoice->getIsActive();
    }

    public function getSendInvoiceEmail()
    {
        return $this->m2Invoice->getSendInvoiceEmail();
    }

    public function jsonData()
    {
        $inputs = json_decode((string) $this->fileSystem->fileGetContents((string)'php://input'), (bool) true);
        $methods = $this->_request->getServer('REQUEST_METHOD');
        
        if (empty($inputs) === true && $methods === 'POST') {
            $post = $this->_request->getPostValue();
                       
            if (array_key_exists('payment', $post)) {
                $inputs['paymentMethod']['additional_data'] = $post['payment'];
            }
        }

        return (array) $inputs;
    }

    public function getXenditSubscriptionCallbackUrl($isMultishipping = false) {
        $baseUrl = $this->getStoreManager()->getStore()->getBaseUrl(UrlInterface::URL_TYPE_LINK) . 'xendit/checkout/subscriptioncallback';

        if ($isMultishipping) {
            $baseUrl .= '?type=multishipping';
        }

        return $baseUrl;
    }

    /**
     * Map card's failure reason to more detailed explanation based on current insight.
     *
     * @param $failureReason
     * @return string
     */
    public function failureReasonInsight($failureReason)
    {
        switch ($failureReason) {
            case 'CARD_DECLINED':
                return 'The card you are trying to use has been declined. Please try again with a different card. Code: 200011';
            case 'STOLEN_CARD':
                return 'The card you are trying to use has been declined. Please try again with a different card. Code: 200013';
            case 'INSUFFICIENT_BALANCE':
                return 'The card you are trying to use has been declined. Please try again with a different card. Code: 200012';
            case 'INVALID_CVN':
                return 'Please verify that all credit card information is correct. Code: 200015';
            case 'INACTIVE_CARD':
                return 'The card you are trying to use has been declined. Please try again with a different card. Code: 200014';
            case 'EXPIRED_CARD':
                return 'The card you are trying to use has expired. Please try again with a different card. Code: 200010';
            case 'PROCESSOR_ERROR':
                return 'We encountered an issue processing your checkout, please contact us. Code: 200009';
            case 'AUTHENTICATION_FAILED':
                return 'Authentication process failed. Please try again. Code: 200001';
            case 'UNEXPECTED_PLUGIN_ISSUE':
                return 'We encountered an issue processing your checkout, please contact us. Code: 999999';
            default: return $failureReason;
        }
    }

    /**
     * Map Magento sales rule action to Xendit's standard type
     *
     * @param $type
     * @return string
     */
    public function mapSalesRuleType($type)
    {
        switch ($type) {
            case 'to_percent':
            case 'by_percent':
                return 'PERCENTAGE';
            case 'to_fixed':
            case 'by_fixed':
                return 'FIXED';
            default:
                return $type;
        }
    }
    
    public function xenditPaymentMethod( $payment ){
        
        //method name => frontend routing
        $listPayment = [
            "cc" => "cc",
            "cchosted" => "cchosted",
            "cc_installment" => "cc_installment",
            "cc_subscription" => "cc_subscription",
            "bcava" => "bca",
            "bniva" => "bni",
            "briva" => "bri",
            "mandiriva" => "mandiri",
            "permatava" => "permata",
            "alfamart" => "alfamart",
            "ovo" => "ovo",
            "dana" => "dana",
            "indomaret" => "indomaret"
        ];

        $response = FALSE;
        if( !!array_key_exists($payment, $listPayment) ){
            $response = $listPayment[$payment];
        }

        return $response; 
    }

    /**
     * Create Order Programatically
     * 
     * @param array $orderData
     * @return array
     * 
    */
    public function createMageOrder($orderData) {
        $store = $this->getStoreManager()->getStore();
        $websiteId = $this->getStoreManager()->getStore()->getWebsiteId();

        $customer = $this->customerFactory->create();
        $customer->setWebsiteId($websiteId);
        $customer->loadByEmail($orderData['email']); //load customer by email address
        
        if(!$customer->getEntityId()){
            //if not available then create this customer 
            $customer->setWebsiteId($websiteId)
                     ->setStore($store)
                     ->setFirstname($orderData['shipping_address']['firstname'])
                     ->setLastname($orderData['shipping_address']['lastname'])
                     ->setEmail($orderData['email']) 
                     ->setPassword($orderData['email']);
            $customer->save();
        }

        $quote = $this->quote->create(); //create object of quote
        $quote->setStore($store);
        
        $customer= $this->customerRepository->getById($customer->getEntityId());
        $quote->setCurrency();
        $quote->assignCustomer($customer); //assign quote to customer
 
        //add items in quote
        foreach($orderData['items'] as $item){
            $_product = $this->objectManager->create(\Magento\Catalog\Model\Product::class);
            $product = $_product->load($item['product_id']);
            $product->setPrice($item['price']);

            $normalizedProductRequest = array_merge(
                ['qty' => intval($item['qty'])],
                array()
            );
            $quote->addProduct(
                $product,
                new DataObject($normalizedProductRequest)
            );
        }
 
        //set address
        $quote->getBillingAddress()->addData($orderData['billing_address']);
        $quote->getShippingAddress()->addData($orderData['shipping_address']);
 
        //collect rates, set shipping & payment method
        $billingAddress = $quote->getBillingAddress();
        $shippingAddress = $quote->getShippingAddress();

        $shippingAddress->setShippingMethod($orderData['shipping_method'])
                        ->setCollectShippingRates(true)
                        ->collectShippingRates();
       
        $billingAddress->setShouldIgnoreValidation(true);
        $shippingAddress->setShouldIgnoreValidation(true);

        $quote->collectTotals();
        $quote->setIsMultiShipping($orderData['is_multishipping']);

        if (!$quote->getIsVirtual()) {
            if (!$billingAddress->getEmail()) {
                $billingAddress->setSameAsBilling(1);
            }
        }

        $quote->setPaymentMethod($orderData['payment']['method']);
        $quote->setInventoryProcessed(true); //update inventory
        $quote->save();
        
        //set required payment data
        $orderData['payment']['cc_number'] = str_replace('X', '0', $orderData['masked_card_number']);
        $quote->getPayment()->importData($orderData['payment']);

        foreach($orderData['payment']['additional_information'] AS $key=>$value) {
            $quote->getPayment()->setAdditionalInformation($key, $value);
        }
        $quote->getPayment()->setAdditionalInformation('xendit_is_subscription', true);

        //collect totals & save quote
        $quote->collectTotals()->save();
 
        //create order from quote
        $order = $this->quoteManagement->submit($quote);

        //update order status
        $orderState = \Magento\Sales\Model\Order::STATE_PROCESSING;
        $message = "Xendit subscription payment completed. Transaction ID: " . $orderData['transaction_id'] . ". ";
        $message .= "Original Order: #" . $orderData['parent_order_id'] . ".";
        $order->setState($orderState)
              ->setStatus($orderState)
              ->addStatusHistoryComment($message);

        $order->save();

        //save order payment details
        $payment = $order->getPayment();
        $payment->setTransactionId($orderData['transaction_id']);
        $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE, null, true);

        //create invoice
        if ($order->canInvoice()) {
            $invoice = $this->objectManager->create('Magento\Sales\Model\Service\InvoiceService')
                                           ->prepareInvoice($order);
            
            if ($invoice->getTotalQty()) {
                $invoice->setTransactionId($orderData['transaction_id']);
                $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
                $invoice->register();
                $invoice->setState(\Magento\Sales\Model\Order\Invoice::STATE_PAID)->save();

                $transaction = $this->objectManager->create('Magento\Framework\DB\Transaction')
                                                   ->addObject($invoice)
                                                   ->addObject($invoice->getOrder());
                $transaction->save();
            }
        }

        //notify customer
        $this->objectManager->create('Magento\Sales\Model\OrderNotifier')->notify($order);
        $order->setEmailSent(1);
        $order->save();

        if($order->getEntityId()){
            $result['order_id'] = $order->getRealOrderId();
        }else{
            $result = array('error' => 1, 'msg' => 'Error creating order');
        }

        return $result;
    }
}
