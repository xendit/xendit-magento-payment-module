<?php

namespace Xendit\M2Invoice\Helper;

use Magento\Catalog\Model\Product;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DB\Transaction as DbTransaction;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Stdlib\DateTime\DateTimeFactory;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderNotifier;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Xendit\M2Invoice\Model\Payment\Xendit;

/**
 * Class Data
 * @package Xendit\M2Invoice\Helper
 */
class Data extends AbstractHelper
{
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
     * @var AssetRepository
     */
    protected $assetRepository;

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
     * @param AssetRepository $assetRepository
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
        OrderNotifier $orderNotifier,
        AssetRepository $assetRepository
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
        $this->assetRepository = $assetRepository;

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
     * @param array $orderIds
     * @return string
     */
    public function getFailureUrl(array $orderIds)
    {
        $parameters = http_build_query([
            'order_ids' => $orderIds
        ]);
        return $this->_getUrl('xendit/checkout/failure', ['_query' => $parameters]);
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
     * Map card's failure reason to more detailed explanation based on current insight.
     *
     * @param $failureReason
     * @return string
     */
    public function failureReasonInsight($failureReason)
    {
        switch ($failureReason) {
            case 'CARD_DECLINED':
            case 'STOLEN_CARD':
                return 'The bank that issued this card declined the payment but didn\'t tell us why.
                Try another card, or try calling your bank to ask why the card was declined.';
            case 'INSUFFICIENT_BALANCE':
                return "Your bank declined this payment due to insufficient balance. Ensure
                that sufficient balance is available, or try another card";
            case 'INVALID_CVN':
                return "Your bank declined the payment due to incorrect card details entered. Try to
                enter your card details again, including expiration date and CVV";
            case 'INACTIVE_CARD':
                return "This card number does not seem to be enabled for eCommerce payments. Try
                another card that is enabled for eCommerce, or ask your bank to enable eCommerce payments for your card.";
            case 'EXPIRED_CARD':
                return "Your bank declined the payment due to the card being expired. Please try
                another card that has not expired.";
            case 'PROCESSOR_ERROR':
                return 'We encountered issue in processing your card. Please try again with another card';
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
            default:
                return $failureReason;
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
     * @return string[]
     */
    public function getXenditPaymentList(): array
    {
        return [
            "cc"                => "cc",
            "bcava"             => "bca",
            "bniva"             => "bni",
            "bjbva"             => "bjb",
            "briva"             => "bri",
            "bsiva"             => "bsi",
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
            "dd_bpi"            => "dd_bpi",
            "seven_eleven"      => "7eleven",
            "dd_ubp"            => "dd_ubp",
            "billease"          => "billease",
            "cebuana"           => "cebuana",
            "dp_palawan"        => "dp_palawan",
            "dp_mlhuillier"     => "dp_mlhuillier",
            "dp_ecpay_loan"     => "dp_ecpay_loan",
            "dp_ecpay_school"   => "dp_ecpay_school",
            "cashalo"           => "cashalo",
            "shopeepayph"       => "shopeepayph",
            "uangme"            => "uangme",
            "astrapay"          => "astrapay",
            "akulaku"           => "akulaku",
            "dd_rcbc"           => "dd_rcbc",
        ];
    }

    /**
     * @param $payment
     * @return false|string
     */
    public function xenditPaymentMethod($payment)
    {
        // method name => frontend routing
        $listPayment = $this->getXenditPaymentList();
        $response = false;
        if (!!array_key_exists($payment, $listPayment)) {
            $response = $listPayment[$payment];
        }

        return $response;
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
     * Refactored function calls
     */
    public function getIsPaymentActive($code)
    {
        return $this->scopeConfig->getValue("payment/$code/active", ScopeInterface::SCOPE_STORE);
    }

    /**
     * @param $code
     * @return mixed
     */
    public function getPaymentTitle($code)
    {
        return $this->scopeConfig->getValue("payment/$code/title", ScopeInterface::SCOPE_STORE);
    }

    /**
     * @param $code
     * @return mixed
     */
    public function getPaymentDescription($code)
    {
        return $this->scopeConfig->getValue("payment/$code/description", ScopeInterface::SCOPE_STORE);
    }

    /**
     * @param $code
     * @return mixed
     */
    public function getPaymentMinOrderAmount($code)
    {
        return $this->scopeConfig->getValue("payment/$code/min_order_total", ScopeInterface::SCOPE_STORE);
    }

    /**
     * @param $code
     * @return mixed
     */
    public function getPaymentMaxOrderAmount($code)
    {
        return $this->scopeConfig->getValue("payment/$code/max_order_total", ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get payment image
     *
     * @param string $code
     * @return false|string|void
     */
    public function getPaymentImage(string $code)
    {
        try {
            $paymentIcon = $this->assetRepository->createAsset('Xendit_M2Invoice::images/methods/' . $code . '.svg');
            if ($paymentIcon && $paymentIcon->getSourceFile()) {
                return $paymentIcon->geturl();
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get Credit & debit images
     *
     * @param string $code
     * @return array|false[]|string[]|void|void[]
     */
    public function getCreditCardImages(string $code)
    {
        $cardImages = $this->scopeConfig->getValue("payment/$code/images", ScopeInterface::SCOPE_STORE);
        if (!empty($cardImages)) {
            return array_filter(
                array_map(function ($cardImage) {
                    try {
                        $cardIcon = $this->assetRepository->createAsset('Xendit_M2Invoice::images/methods/cards/' . $cardImage . '.svg');
                        if ($cardIcon && $cardIcon->getSourceFile()) {
                            return $cardIcon->geturl();
                        }
                    } catch (\Exception $e) {
                        return false;
                    }
                }, explode(",", $cardImages) ?? []),
                function ($item) {
                    return !!$item;
                }
            );
        }
    }

    /**
     * @param string $payment
     * @param string $currency
     * @return bool
     */
    public function isAvailableOnCurrency(string $payment, string $currency): bool
    {
        $paymentCurrencies = $this->scopeConfig->getValue('payment/' . $payment . '/currency', ScopeInterface::SCOPE_STORE);
        if (is_null($paymentCurrencies) || in_array($currency, array_map("trim", explode(',', $paymentCurrencies) ?? []))) {
            return true;
        }
        return false;
    }

    /**
     * @param $amount
     * @return false|float
     */
    public function truncateDecimal($amount)
    {
        return floor((double)$amount);
    }

    /**
     * @param Order $order
     * @return array
     */
    public function extractXenditInvoiceCustomerFromOrder(Order $order): array
    {
        $shippingAddress = $order->getShippingAddress();
        $customerObject = [
            'given_names' => $order->getCustomerFirstname(),
            'surname' => $order->getCustomerLastname(),
            'email' => $order->getCustomerEmail(),
            'mobile_number' => $shippingAddress->getTelephone()
        ];

        $customerObject = array_filter($customerObject);
        $addressObject = $this->extractXenditInvoiceCustomerAddress($shippingAddress);
        if (!empty($addressObject)) {
            $customerObject['addresses'] = [$addressObject];
        }
        return $customerObject;
    }

    /**
     * @param $shippingAddress
     * @return array
     */
    public function extractXenditInvoiceCustomerAddress($shippingAddress): array
    {
        if (empty($shippingAddress)) {
            return [];
        }

        $address = [
            'street_line1' => $shippingAddress->getData('street'),
            'city' => $shippingAddress->getData('city'),
            'state' => $shippingAddress->getData('region'),
            'postal_code' => $shippingAddress->getData('postcode'),
            'country' => $shippingAddress->getData('country_id')
        ];

        return array_filter($address);
    }
}
