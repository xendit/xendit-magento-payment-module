<?php

namespace Xendit\M2Invoice\Helper;

use Magento\Catalog\Model\Product;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DataObject;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteManagement;
use Magento\Store\Model\StoreManagerInterface;
use Xendit\M2Invoice\Model\Payment\Xendit;
use Magento\Framework\Stdlib\DateTime\DateTimeFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\DB\Transaction as DbTransaction;
use Magento\Sales\Model\OrderNotifier;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;

/**
 * Class Data
 * @package Xendit\M2Invoice\Helper
 */
class Data extends AbstractHelper
{
    /*
     *  Alfamart
     */
    const XML_PATH_ALFAMART_ACTIVE          = 'payment/alfamart/active';
    const XML_PATH_ALFAMART_TITLE           = 'payment/alfamart/title';
    const XML_PATH_ALFAMART_MIN_AMOUNT      = 'payment/alfamart/min_order_total';
    const XML_PATH_ALFAMART_MAX_AMOUNT      = 'payment/alfamart/max_order_total';
    const XML_PATH_ALFAMART_DESCRIPTION     = 'payment/alfamart/description';

    /*
     *  BCAVA
     */
    const XML_PATH_BCAVA_ACTIVE             = 'payment/bcava/active';
    const XML_PATH_BCAVA_TITLE              = 'payment/bcava/title';
    const XML_PATH_BCAVA_MIN_AMOUNT         = 'payment/bcava/min_order_total';
    const XML_PATH_BCAVA_MAX_AMOUNT         = 'payment/bcava/max_order_total';
    const XML_PATH_BCAVA_DESCRIPTION        = 'payment/bcava/description';

    /*
     *  BNIVA
     */
    const XML_PATH_BNIVA_ACTIVE             = 'payment/bniva/active';
    const XML_PATH_BNIVA_TITLE              = 'payment/bniva/title';
    const XML_PATH_BNIVA_MIN_AMOUNT         = 'payment/bniva/min_order_total';
    const XML_PATH_BNIVA_MAX_AMOUNT         = 'payment/bniva/max_order_total';
    const XML_PATH_BNIVA_DESCRIPTION        = 'payment/bniva/description';

    /*
     *  BRIVA
     */
    const XML_PATH_BRIVA_ACTIVE             = 'payment/briva/active';
    const XML_PATH_BRIVA_TITLE              = 'payment/briva/title';
    const XML_PATH_BRIVA_MIN_AMOUNT         = 'payment/briva/min_order_total';
    const XML_PATH_BRIVA_MAX_AMOUNT         = 'payment/briva/max_order_total';
    const XML_PATH_BRIVA_DESCRIPTION        = 'payment/briva/description';

    /*
     *  CC Hosted
     */
    const XML_PATH_CC_ACTIVE          = 'payment/cc/active';
    const XML_PATH_CC_TITLE           = 'payment/cc/title';
    const XML_PATH_CC_MIN_AMOUNT      = 'payment/cc/min_order_total';
    const XML_PATH_CC_MAX_AMOUNT      = 'payment/cc/max_order_total';
    const XML_PATH_CC_DESCRIPTION     = 'payment/cc/description';

    /*
     *  CC Subscription
     */
    const XML_PATH_CC_SUBSCRIPTION_ACTIVE           = 'payment/cc_subscription/active';
    const XML_PATH_CC_SUBSCRIPTION_TITLE            = 'payment/cc_subscription/title';
    const XML_PATH_CC_SUBSCRIPTION_MIN_AMOUNT       = 'payment/cc_subscription/min_order_total';
    const XML_PATH_CC_SUBSCRIPTION_MAX_AMOUNT       = 'payment/cc_subscription/max_order_total';
    const XML_PATH_CC_SUBSCRIPTION_DESCRIPTION      = 'payment/cc_subscription/description';
    const XML_PATH_CC_SUBSCRIPTION_INTERVAL         = 'payment/cc_subscription/interval';
    const XML_PATH_CC_SUBSCRIPTION_INTERVAL_COUNT   = 'payment/cc_subscription/interval_count';

    /*
     *  DD BRI
     */
    const XML_PATH_DDBRI_ACTIVE             = 'payment/dd_bri/active';
    const XML_PATH_DDBRI_TITLE              = 'payment/dd_bri/title';
    const XML_PATH_DDBRI_MIN_AMOUNT         = 'payment/dd_bri/min_order_total';
    const XML_PATH_DDBRI_MAX_AMOUNT         = 'payment/dd_bri/max_order_total';
    const XML_PATH_DDBRI_DESCRIPTION        = 'payment/dd_bri/description';

    /*
     *  Dana
     */
    const XML_PATH_DANA_ACTIVE              = 'payment/dana/active';
    const XML_PATH_DANA_TITLE               = 'payment/dana/title';
    const XML_PATH_DANA_MIN_AMOUNT          = 'payment/dana/min_order_total';
    const XML_PATH_DANA_MAX_AMOUNT          = 'payment/dana/max_order_total';
    const XML_PATH_DANA_DESCRIPTION         = 'payment/dana/description';

    /*
     *  Indomaret
     */
    const XML_PATH_INDOMARET_ACTIVE         = 'payment/indomaret/active';
    const XML_PATH_INDOMARET_TITLE          = 'payment/indomaret/title';
    const XML_PATH_INDOMARET_MIN_AMOUNT     = 'payment/indomaret/min_order_total';
    const XML_PATH_INDOMARET_MAX_AMOUNT     = 'payment/indomaret/max_order_total';
    const XML_PATH_INDOMARET_DESCRIPTION    = 'payment/indomaret/description';

    /*
     *  Kredivo
     */
    const XML_PATH_KREDIVO_ACTIVE           = 'payment/kredivo/active';
    const XML_PATH_KREDIVO_TITLE            = 'payment/kredivo/title';
    const XML_PATH_KREDIVO_MIN_AMOUNT       = 'payment/kredivo/min_order_total';
    const XML_PATH_KREDIVO_MAX_AMOUNT       = 'payment/kredivo/max_order_total';
    const XML_PATH_KREDIVO_DESCRIPTION      = 'payment/kredivo/description';

    /*
     *  Linkaja
     */
    const XML_PATH_LINKAJA_ACTIVE           = 'payment/linkaja/active';
    const XML_PATH_LINKAJA_TITLE            = 'payment/linkaja/title';
    const XML_PATH_LINKAJA_MIN_AMOUNT       = 'payment/linkaja/min_order_total';
    const XML_PATH_LINKAJA_MAX_AMOUNT       = 'payment/linkaja/max_order_total';
    const XML_PATH_LINKAJA_DESCRIPTION      = 'payment/linkaja/description';

    /*
     *  MANDIRIVA
     */
    const XML_PATH_MANDIRIVA_ACTIVE         = 'payment/mandiriva/active';
    const XML_PATH_MANDIRIVA_TITLE          = 'payment/mandiriva/title';
    const XML_PATH_MANDIRIVA_MIN_AMOUNT     = 'payment/mandiriva/min_order_total';
    const XML_PATH_MANDIRIVA_MAX_AMOUNT     = 'payment/mandiriva/max_order_total';
    const XML_PATH_MANDIRIVA_DESCRIPTION    = 'payment/mandiriva/description';

    /*
     *  Ovo
     */
    const XML_PATH_OVO_ACTIVE               = 'payment/ovo/active';
    const XML_PATH_OVO_TITLE                = 'payment/ovo/title';
    const XML_PATH_OVO_MIN_AMOUNT           = 'payment/ovo/min_order_total';
    const XML_PATH_OVO_MAX_AMOUNT           = 'payment/ovo/max_order_total';
    const XML_PATH_OVO_DESCRIPTION          = 'payment/ovo/description';

    /*
     *  PERMATAVA
     */
    const XML_PATH_PERMATAVA_ACTIVE         = 'payment/permatava/active';
    const XML_PATH_PERMATAVA_TITLE          = 'payment/permatava/title';
    const XML_PATH_PERMATAVA_MIN_AMOUNT     = 'payment/permatava/min_order_total';
    const XML_PATH_PERMATAVA_MAX_AMOUNT     = 'payment/permatava/max_order_total';
    const XML_PATH_PERMATAVA_DESCRIPTION    = 'payment/permatava/description';

    /*
     *  QRIS
     */
    const XML_PATH_QRIS_ACTIVE           = 'payment/qris/active';
    const XML_PATH_QRIS_TITLE            = 'payment/qris/title';
    const XML_PATH_QRIS_MIN_AMOUNT       = 'payment/qris/min_order_total';
    const XML_PATH_QRIS_MAX_AMOUNT       = 'payment/qris/max_order_total';
    const XML_PATH_QRIS_DESCRIPTION      = 'payment/qris/description';

    /*
     *  ShopeePay
     */
    const XML_PATH_SHOPEEPAY_ACTIVE               = 'payment/shopeepay/active';
    const XML_PATH_SHOPEEPAY_TITLE                = 'payment/shopeepay/title';
    const XML_PATH_SHOPEEPAY_MIN_AMOUNT           = 'payment/shopeepay/min_order_total';
    const XML_PATH_SHOPEEPAY_MAX_AMOUNT           = 'payment/shopeepay/max_order_total';
    const XML_PATH_SHOPEEPAY_DESCRIPTION          = 'payment/shopeepay/description';

    /*
     *  PayMaya
     */
    const XML_PATH_PAYMAYA_ACTIVE               = 'payment/paymaya/active';
    const XML_PATH_PAYMAYA_TITLE                = 'payment/paymaya/title';
    const XML_PATH_PAYMAYA_MIN_AMOUNT           = 'payment/paymaya/min_order_total';
    const XML_PATH_PAYMAYA_MAX_AMOUNT           = 'payment/paymaya/max_order_total';
    const XML_PATH_PAYMAYA_DESCRIPTION          = 'payment/paymaya/description';

    /*
     *  GCash
     */
    const XML_PATH_GCASH_ACTIVE               = 'payment/gcash/active';
    const XML_PATH_GCASH_TITLE                = 'payment/gcash/title';
    const XML_PATH_GCASH_MIN_AMOUNT           = 'payment/gcash/min_order_total';
    const XML_PATH_GCASH_MAX_AMOUNT           = 'payment/gcash/max_order_total';
    const XML_PATH_GCASH_DESCRIPTION          = 'payment/gcash/description';

    /*
     *  GrabPay
     */
    const XML_PATH_GRABPAY_ACTIVE               = 'payment/grabpay/active';
    const XML_PATH_GRABPAY_TITLE                = 'payment/grabpay/title';
    const XML_PATH_GRABPAY_MIN_AMOUNT           = 'payment/grabpay/min_order_total';
    const XML_PATH_GRABPAY_MAX_AMOUNT           = 'payment/grabpay/max_order_total';
    const XML_PATH_GRABPAY_DESCRIPTION          = 'payment/grabpay/description';

    /*
     *  DD DPI
     */
    const XML_PATH_DDBPI_ACTIVE               = 'payment/dd_bpi/active';
    const XML_PATH_DDBPI_TITLE                = 'payment/dd_bpi/title';
    const XML_PATH_DDBPI_MIN_AMOUNT           = 'payment/dd_bpi/min_order_total';
    const XML_PATH_DDBPI_MAX_AMOUNT           = 'payment/dd_bpi/max_order_total';
    const XML_PATH_DDBPI_DESCRIPTION          = 'payment/dd_bpi/description';

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Xendit
     */
    private $xendit;

    /**
     * @var File
     */
    private $fileSystem;

    /**
     * @var Product
     */
    private $product;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var CustomerFactory
     */
    private $customerFactory;

    /**
     * @var QuoteFactory
     */
    private $quote;

    /**
     * @var QuoteManagement
     */
    private $quoteManagement;

    /**
     * @var DateTimeFactory
     */
    private $dateTimeFactory;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var InvoiceService
     */
    protected $invoiceService;

    /**
     * @var DbTransaction
     */
    protected $dbTransaction;

    /**
     * @var
     */
    protected $orderNotifier;

    /**
     * Data constructor.
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param Xendit $xendit
     * @param File $fileSystem
     * @param Product $product
     * @param CustomerRepositoryInterface $customerRepository
     * @param CustomerFactory $customerFactory
     * @param QuoteFactory $quote
     * @param QuoteManagement $quoteManagement
     * @param DateTimeFactory $dateTimeFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param InvoiceService $invoiceService
     * @param DbTransaction $dbTransaction
     * @param OrderNotifier $orderNotifier
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        Xendit $xendit,
        File $fileSystem,
        Product $product,
        CustomerRepositoryInterface $customerRepository,
        CustomerFactory $customerFactory,
        QuoteFactory $quote,
        QuoteManagement $quoteManagement,
        DateTimeFactory $dateTimeFactory,
        ScopeConfigInterface $scopeConfig,
        InvoiceService $invoiceService,
        DbTransaction $dbTransaction,
        OrderNotifier $orderNotifier
    ) {
        $this->storeManager = $storeManager;
        $this->xendit = $xendit;
        $this->fileSystem = $fileSystem;
        $this->product = $product;
        $this->customerRepository = $customerRepository;
        $this->customerFactory = $customerFactory;
        $this->quote = $quote;
        $this->quoteManagement = $quoteManagement;
        $this->dateTimeFactory = $dateTimeFactory;
        $this->scopeConfig = $scopeConfig;
        $this->invoiceService = $invoiceService;
        $this->dbTransaction = $dbTransaction;
        $this->orderNotifier = $orderNotifier;

        parent::__construct($context);
    }

    /**
     * @return StoreManagerInterface
     */
    protected function getStoreManager()
    {
        return $this->storeManager;
    }

    /**
     * @return mixed
     */
    public function getCheckoutUrl()
    {
        return $this->xendit->getConfigData('xendit_url');
    }

    /**
     * @return mixed
     */
    public function getUiUrl()
    {
        return $this->xendit->getUiUrl();
    }

    /**
     * @param bool $isMultishipping
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getSuccessUrl($isMultishipping = false)
    {
        $baseUrl = $this->getStoreManager()->getStore()->getBaseUrl() . 'xendit/checkout/success';
        if ($isMultishipping) {
            $baseUrl .= '?type=multishipping';
        }
        return $baseUrl;
    }

    /**
     * @param $orderId
     * @param bool $isMultishipping
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getFailureUrl($orderId, $isMultishipping = false)
    {
        $baseUrl = $this->getStoreManager()->getStore()->getBaseUrl() . "xendit/checkout/failure?order_id=$orderId";
        if ($isMultishipping) {
            $baseUrl .= '&type=multishipping';
        }
        return $baseUrl;
    }

    /**
     * @param $orderId
     * @param bool $isMultishipping
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getThreeDSResultUrl($orderId, $isMultishipping = false)
    {
        $baseUrl = $this->getStoreManager()->getStore()->getBaseUrl() . "xendit/checkout/threedsresult?order_id=$orderId";
        if ($isMultishipping) {
            $baseUrl .= "&type=multishipping";
        }
        return $baseUrl;
    }

    /**
     * @param $orderId
     * @param bool $duplicate
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getExternalId($orderId, $duplicate = false)
    {
        $defaultExtId = $this->getExternalIdPrefix() . "-$orderId";
        if ($duplicate) {
            return uniqid() . "-" . $defaultExtId;
        }
        return $defaultExtId;
    }

    /**
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getExternalIdPrefix()
    {
        return $this->xendit->getConfigData('external_id_prefix') . "-" . $this->getStoreName();
    }

    /**
     * @return bool|string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getStoreName()
    {
        return substr(preg_replace("/[^a-z0-9]/mi", "", $this->getStoreManager()->getStore()->getName()), 0, 20);
    }

    /**
     * @return mixed
     */
    public function getApiKey()
    {
        return $this->xendit->getApiKey();
    }

    /**
     * @return mixed
     */
    public function getPublicApiKey()
    {
        return $this->xendit->getPublicApiKey();
    }

    /**
     * @return mixed
     */
    public function getEnvironment()
    {
        return $this->xendit->getEnvironment();
    }

    /**
     * @return mixed
     */
    public function getCardPaymentType()
    {
        return $this->xendit->getCardPaymentType();
    }

    /**
     * @return mixed
     */
    public function getAllowedMethod()
    {
        return $this->xendit->getAllowedMethod();
    }

    /**
     * @return mixed
     */
    public function getChosenMethods()
    {
        return $this->xendit->getChosenMethods();
    }

    /**
     * @return array
     */
    public function getEnabledPromo()
    {
        return $this->xendit->getEnabledPromo();
    }

    /**
     * @return mixed
     */
    public function getIsActive()
    {
        return $this->xendit->getIsActive();
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\FileSystemException
     */
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

    /**
     * @param bool $isMultishipping
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getXenditSubscriptionCallbackUrl($isMultishipping = false) {
        $baseUrl = $this->getStoreManager()->getStore()->getBaseUrl(UrlInterface::URL_TYPE_LINK) . 'xendit/checkout/subscriptioncallback';

        if ($isMultishipping) {
            $baseUrl .= '?type=multishipping';
        }

        return $baseUrl;
    }

    /**
     * @param bool $isMultishipping
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getCCCallbackUrl($isMultishipping = false) {
        $baseUrl = $this->getStoreManager()->getStore()->getBaseUrl(UrlInterface::URL_TYPE_LINK) . 'xendit/checkout/cccallback';

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
            case 'STOLEN_CARD': return 'The bank that issued this card declined the payment but didn\'t tell us why.
                Try another card, or try calling your bank to ask why the card was declined.';
            case 'INSUFFICIENT_BALANCE': return "Your bank declined this payment due to insufficient balance. Ensure
                that sufficient balance is available, or try another card";
            case 'INVALID_CVN': return "Your bank declined the payment due to incorrect card details entered. Try to
                enter your card details again, including expiration date and CVV";
            case 'INACTIVE_CARD': return "This card number does not seem to be enabled for eCommerce payments. Try
                another card that is enabled for eCommerce, or ask your bank to enable eCommerce payments for your card.";
            case 'EXPIRED_CARD': return "Your bank declined the payment due to the card being expired. Please try
                another card that has not expired.";
            case 'PROCESSOR_ERROR': return 'We encountered issue in processing your card. Please try again with another card';
            case 'USER_DID_NOT_AUTHORIZE_THE_PAYMENT':
                return 'Please complete the payment request within 60 seconds.';
            case 'USER_DECLINED_THE_TRANSACTION':
                return 'You rejected the payment request, please try again when needed.';
            case 'PHONE_NUMBER_NOT_REGISTERED':
                return 'Your number is not registered in OVO, please register first or contact OVO Customer Service.';
            case 'EXTERNAL_ERROR':
                return 'There is a technical issue happens on OVO, please contact the merchant to solve this issue.';
            case 'SENDING_TRANSACTION_ERROR':
                return 'Your transaction is not sent to OVO, please try again.';
            case 'EWALLET_APP_UNREACHABLE':
                return 'Do you have OVO app on your phone? Please check your OVO app on your phone and try again.';
            case 'REQUEST_FORBIDDEN_ERROR':
                return 'Your merchant disable OVO payment from his side, please contact your merchant to re-enable it
                    before trying it again.';
            case 'DEVELOPMENT_MODE_PAYMENT_ACKNOWLEDGED':
                return 'Development mode detected. Please refer to our documentations for successful payment
                    simulation';
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

    /**
     * @param $payment
     * @return bool|mixed
     */
    public function xenditPaymentMethod( $payment ){

        //method name => frontend routing
        $listPayment = [
            "cc"                => "cc",
            "cc_subscription"   => "cc_subscription",
            "bcava"             => "bca",
            "bniva"             => "bni",
            "briva"             => "bri",
            "mandiriva"         => "mandiri",
            "permatava"         => "permata",
            "alfamart"          => "alfamart",
            "ovo"               => "ovo",
            "dana"              => "dana",
            "linkaja"           => "linkaja",
            "shopeepay"         => "shopeepay",
            "indomaret"         => "indomaret",
            "qris"              => "qris",
            "dd_bri"            => "dd_bri",
            "kredivo"           => "kredivo",
            "gcash"             => "gcash",
            "grabpay"           => "grabpay",
            "paymaya"           => "paymaya",
            "dd_bpi"            => "dd_bpi"
        ];

        $response = FALSE;
        if (!!array_key_exists($payment, $listPayment)) {
            $response = $listPayment[$payment];
        }

        return $response;
    }

    /**
     * Create Order Programatically
     *
     * @param array $orderData
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function createMageOrder($orderData) {
        $store = $this->getStoreManager()->getStore();
        $websiteId = $this->getStoreManager()->getStore()->getWebsiteId();

        $customer = $this->customerFactory->create();
        $customer->setWebsiteId($websiteId);
        $customer->loadByEmail($orderData['email']); //load customer by email address

        if (!$customer->getEntityId()) {
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
        foreach ($orderData['items'] as $item) {
            $product = $this->product->load($item['product_id']);
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

        foreach ($orderData['payment']['additional_information'] AS $key => $value) {
            $quote->getPayment()->setAdditionalInformation($key, $value);
        }
        $quote->getPayment()->setAdditionalInformation('xendit_is_subscription', true);

        //collect totals & save quote
        $quote->collectTotals()->save();

        //create order from quote
        $order = $this->quoteManagement->submit($quote);

        //update order status
        $orderState = Order::STATE_PROCESSING;
        $message = "Xendit subscription payment completed. Transaction ID: " . $orderData['transaction_id'] . ". ";
        $message .= "Original Order: #" . $orderData['parent_order_id'] . ".";
        $order->setState($orderState)
              ->setStatus($orderState)
              ->addStatusHistoryComment($message);

        $order->save();

        //save order payment details
        $payment = $order->getPayment();
        $payment->setTransactionId($orderData['transaction_id']);
        $payment->addTransaction(Transaction::TYPE_CAPTURE, null, true);

        //create invoice
        if ($order->canInvoice()) {
            $invoice = $this->invoiceService->prepareInvoice($order);

            if ($invoice->getTotalQty()) {
                if (isset($orderData['transaction_id'])) {
                    $invoice->setTransactionId($orderData['transaction_id']);
                }
                $invoice->setRequestedCaptureCase(Invoice::CAPTURE_OFFLINE);
                $invoice->register();
                $invoice->setState(Invoice::STATE_PAID)->save();

                $transaction = $this->dbTransaction->addObject($invoice)->addObject($invoice->getOrder());
                $transaction->save();
            }
        }

        //notify customer
        $this->orderNotifier->notify($order);
        $order->setEmailSent(1);
        $order->save();

        if ($order->getEntityId()) {
            $result['order_id'] = $order->getRealOrderId();
        } else {
            $result = array('error' => 1, 'msg' => 'Error creating order');
        }

        return $result;
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return true;
    }

    /**
     * @param $date
     * @return false|string
     */
    protected function convertDateTime($date)
    {
        return gmdate(DATE_W3C, $date);
    }

    /**
     * @return mixed
     */
    public function getAlfamartActive()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_ALFAMART_ACTIVE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getAlfamartTitle()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_ALFAMART_TITLE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getAlfamartDescription()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_ALFAMART_DESCRIPTION, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getAlfamartMinOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_ALFAMART_MIN_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getAlfamartMaxOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_ALFAMART_MAX_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getBcaVaActive()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_BCAVA_ACTIVE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getBcaVaTitle()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_BCAVA_MAX_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getBcaVaDescription()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_BCAVA_DESCRIPTION, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getBcaVaMinOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_BCAVA_MIN_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getBcaVaMaxOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_BCAVA_MAX_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getBniVaActive()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_BNIVA_ACTIVE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getBniVaTitle()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_BNIVA_TITLE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getBniVaDescription()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_BNIVA_DESCRIPTION, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getBniVaMinOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_BNIVA_MIN_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getBniVaMaxOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_BNIVA_MAX_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getBriVaActive()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_BRIVA_ACTIVE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getBriVaTitle()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_BRIVA_TITLE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getBriVaDescription()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_BRIVA_DESCRIPTION, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getBriVaMinOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_BRIVA_MIN_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getBriVaMaxOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_BRIVA_MAX_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getCcActive()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_CC_ACTIVE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getCcTitle()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_CC_TITLE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getCcDescription()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_CC_DESCRIPTION, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getCcMinOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_CC_MIN_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getCcMaxOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_CC_MAX_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getCcSubscriptionActive()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_CC_SUBSCRIPTION_ACTIVE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getCcSubscriptionTitle()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_CC_SUBSCRIPTION_TITLE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getCcSubscriptionDescription()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_CC_SUBSCRIPTION_DESCRIPTION, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getCcSubscriptionMinOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_CC_SUBSCRIPTION_MIN_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getCcSubscriptionMaxOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_CC_SUBSCRIPTION_MAX_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getCcSubscriptionInterval()
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_CC_SUBSCRIPTION_INTERVAL, ScopeInterface::SCOPE_STORE);
        return $value ?: 'MONTH';
    }

    /**
     * @return mixed
     */
    public function getCcSubscriptionIntervalCount()
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_CC_SUBSCRIPTION_INTERVAL_COUNT, ScopeInterface::SCOPE_STORE);
        return $value ?: 1;
    }

    /**
     * @return mixed
     */
    public function getDanaActive()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_DANA_ACTIVE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getDanaTitle()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_DANA_TITLE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getDanaDescription()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_DANA_DESCRIPTION, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getDanaMinOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_DANA_MIN_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getDanaMaxOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_DANA_MAX_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getDdBriActive()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_DDBRI_ACTIVE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getDdBriTitle()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_DDBRI_TITLE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getDdBriDescription()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_DDBRI_DESCRIPTION, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getDdBriMinOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_DDBRI_MIN_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getDdBriMaxOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_DDBRI_MAX_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getIndomaretActive()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_INDOMARET_ACTIVE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getIndomaretTitle()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_INDOMARET_TITLE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getIndomaretDescription()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_INDOMARET_DESCRIPTION, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getIndomaretMinOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_INDOMARET_MIN_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getIndomaretMaxOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_INDOMARET_MAX_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getKredivoActive()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_KREDIVO_ACTIVE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getKredivoTitle()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_KREDIVO_TITLE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getKredivoDescription()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_KREDIVO_DESCRIPTION, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getKredivoMinOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_KREDIVO_MIN_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getKredivoMaxOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_KREDIVO_MAX_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getLinkajaActive()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_LINKAJA_ACTIVE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getLinkajaTitle()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_LINKAJA_TITLE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getLinkajaDescription()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_LINKAJA_DESCRIPTION, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getLinkajaMinOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_LINKAJA_MIN_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getLinkajaMaxOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_LINKAJA_MAX_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getMandiriVaActive()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_MANDIRIVA_ACTIVE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getMandiriVaTitle()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_MANDIRIVA_TITLE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getMandiriVaDescription()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_MANDIRIVA_DESCRIPTION, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getMandiriVaMinOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_MANDIRIVA_MIN_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getMandiriVaMaxOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_MANDIRIVA_MAX_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getOvoActive()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_OVO_ACTIVE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getOvoTitle()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_OVO_TITLE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getOvoDescription()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_OVO_DESCRIPTION, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getOvoMinOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_OVO_MIN_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getOvoMaxOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_OVO_MAX_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getShopeePayActive()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_SHOPEEPAY_ACTIVE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getShopeePayTitle()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_SHOPEEPAY_TITLE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getShopeePayDescription()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_SHOPEEPAY_DESCRIPTION, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getShopeePayMinOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_SHOPEEPAY_MIN_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getShopeePayMaxOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_SHOPEEPAY_MAX_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getPermataVaActive()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_PERMATAVA_ACTIVE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getPermataVaTitle()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_PERMATAVA_TITLE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getPermataVaDescription()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_PERMATAVA_DESCRIPTION, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getPermataVaMinOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_PERMATAVA_MIN_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getPermataVaMaxOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_PERMATAVA_MAX_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getQrisActive()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_QRIS_ACTIVE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getQrisTitle()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_QRIS_TITLE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getQrisDescription()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_QRIS_DESCRIPTION, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getQrisMinOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_QRIS_MIN_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getQrisMaxOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_QRIS_MAX_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getPayMayaActive()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_PAYMAYA_ACTIVE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getPayMayaTitle()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_PAYMAYA_TITLE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getPayMayaDescription()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_PAYMAYA_DESCRIPTION, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getPayMayaMinOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_PAYMAYA_MIN_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getPayMayaMaxOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_PAYMAYA_MAX_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getGCashActive()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_GCASH_ACTIVE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getGCashTitle()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_GCASH_TITLE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getGCashDescription()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_GCASH_DESCRIPTION, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getGCashMinOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_GCASH_MIN_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getGCashMaxOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_GCASH_MAX_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getGrabPayActive()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_GRABPAY_ACTIVE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getGrabPayTitle()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_GRABPAY_TITLE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getGrabPayDescription()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_GRABPAY_DESCRIPTION, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getGrabPayMinOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_GRABPAY_MIN_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getGrabPayMaxOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_GRABPAY_MAX_AMOUNT, ScopeInterface::SCOPE_STORE);
    }


    /**
     * @return mixed
     */
    public function getDdBpiActive()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_DDBPI_ACTIVE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getDdBpiTitle()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_DDBPI_TITLE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getDdBpiDescription()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_DDBPI_DESCRIPTION, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getDdBpiMinOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_DDBPI_MIN_AMOUNT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getDdBpiMaxOrderAmount()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_DDBPI_MAX_AMOUNT, ScopeInterface::SCOPE_STORE);
    }
}
