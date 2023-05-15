<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Catalog\Model\CategoryFactory;
use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\DB\Transaction as DbTransaction;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Multishipping\Model\Checkout\Type\Multishipping;
use Magento\Multishipping\Model\Checkout\Type\Multishipping\State;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Category;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Xendit\M2Invoice\Helper\ApiRequest;
use Xendit\M2Invoice\Helper\Checkout;
use Xendit\M2Invoice\Helper\Crypto;
use Xendit\M2Invoice\Helper\Data;
use Xendit\M2Invoice\Helper\ErrorHandler;
use Xendit\M2Invoice\Helper\Metric;
use Xendit\M2Invoice\Logger\Logger as XenditLogger;

/**
 * Class AbstractAction
 * @package Xendit\M2Invoice\Controller\Checkout
 */
abstract class AbstractAction extends Action
{
    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var CategoryFactory
     */
    private $categoryFactory;

    /**
     * @var OrderFactory
     */
    private $orderFactory;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Data
     */
    private $dataHelper;

    /**
     * @var Crypto
     */
    private $cryptoHelper;

    /**
     * @var Checkout
     */
    private $checkoutHelper;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepo;

    /**
     * @var ApiRequest
     */
    private $apiHelper;

    /**
     * @var \Magento\Framework\Controller\Result\RedirectFactory
     */
    protected $resultRedirectFactory;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var JsonFactory
     */
    private $jsonResultFactory;

    /**
     * @var CookieManagerInterface
     */
    private $cookieManager;

    /**
     * @var InvoiceService
     */
    private $invoiceService;

    /**
     * @var DbTransaction
     */
    private $dbTransaction;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var ErrorHandler
     */
    private $errorHandler;

    /**
     * @var State
     */
    private $state;

    /**
     * @var Multishipping
     */
    private $multishipping;

    /**
     * @var Metric
     */
    protected $metricHelper;

    /**
     * AbstractAction constructor.
     *
     * @param Session $checkoutSession
     * @param Context $context
     * @param CategoryFactory $categoryFactory
     * @param OrderFactory $orderFactory
     * @param LoggerInterface $logger
     * @param Data $dataHelper
     * @param Crypto $cryptoHelper
     * @param Checkout $checkoutHelper
     * @param OrderRepositoryInterface $orderRepo
     * @param ApiRequest $apiHelper
     * @param StoreManagerInterface $storeManager
     * @param CartRepositoryInterface $quoteRepository
     * @param JsonFactory $jsonResultFactory
     * @param CookieManagerInterface $cookieManager
     * @param InvoiceService $invoiceService
     * @param DbTransaction $dbTransaction
     * @param CustomerSession $customerSession
     * @param ErrorHandler $errorHandler
     * @param State $state
     * @param Multishipping $multishipping
     * @param Metric $metricHelper
     */
    public function __construct(
        Session $checkoutSession,
        Context $context,
        CategoryFactory $categoryFactory,
        OrderFactory $orderFactory,
        XenditLogger $logger,
        Data $dataHelper,
        Crypto $cryptoHelper,
        Checkout $checkoutHelper,
        OrderRepositoryInterface $orderRepo,
        ApiRequest $apiHelper,
        StoreManagerInterface $storeManager,
        CartRepositoryInterface $quoteRepository,
        JsonFactory $jsonResultFactory,
        CookieManagerInterface $cookieManager,
        InvoiceService $invoiceService,
        DbTransaction $dbTransaction,
        CustomerSession $customerSession,
        ErrorHandler $errorHandler,
        State $state,
        Multishipping $multishipping,
        Metric $metricHelper
    ) {
        parent::__construct($context);

        $this->checkoutSession = $checkoutSession;
        $this->context = $context;
        $this->categoryFactory = $categoryFactory;
        $this->orderFactory = $orderFactory;
        $this->logger = $logger;
        $this->dataHelper = $dataHelper;
        $this->cryptoHelper = $cryptoHelper;
        $this->checkoutHelper = $checkoutHelper;
        $this->messageManager = $context->getMessageManager();
        $this->orderRepo = $orderRepo;
        $this->apiHelper = $apiHelper;
        $this->resultRedirectFactory = $context->getResultRedirectFactory();
        $this->storeManager = $storeManager;
        $this->quoteRepository = $quoteRepository;
        $this->jsonResultFactory = $jsonResultFactory;
        $this->cookieManager = $cookieManager;
        $this->invoiceService = $invoiceService;
        $this->dbTransaction = $dbTransaction;
        $this->customerSession = $customerSession;
        $this->errorHandler = $errorHandler;
        $this->state = $state;
        $this->multishipping = $multishipping;
        $this->metricHelper = $metricHelper;
    }

    /**
     * @return Context
     */
    protected function getContext()
    {
        return $this->context;
    }

    /**
     * @return Session
     */
    protected function getCheckoutSession()
    {
        return $this->checkoutSession;
    }

    /**
     * @return LoggerInterface
     */
    protected function getLogger()
    {
        return $this->logger;
    }

    /**
     * @return InvoiceService
     */
    protected function getInvoiceService()
    {
        return $this->invoiceService;
    }

    /**
     * @return DbTransaction
     */
    protected function getDbTransaction()
    {
        return $this->dbTransaction;
    }

    /**
     * @return CustomerSession
     */
    protected function getCustomerSession()
    {
        return $this->customerSession;
    }

    /**
     * @return Order|null
     */
    protected function getOrder()
    {
        $orderId = $this->checkoutSession->getLastRealOrderId();

        if (!isset($orderId)) {
            return null;
        }

        return $this->getOrderById($orderId);
    }

    /**
     * @param $categoryId
     * @return Category|null
     */
    protected function getCategoryById($categoryId)
    {
        $category = $this->categoryFactory->create()->loadByIncrementId($categoryId);

        if (!$category->getId()) {
            return null;
        }

        return $category;
    }

    /**
     * @param $orderId
     * @return Order|null
     */
    protected function getOrderById($orderId)
    {
        $order = $this->orderFactory->create()->loadByIncrementId($orderId);

        if (!$order->getId()) {
            return null;
        }

        return $order;
    }

    /**
     * @return Data
     */
    protected function getDataHelper()
    {
        return $this->dataHelper;
    }

    /**
     * @return ErrorHandler
     */
    protected function getErrorHandler()
    {
        return $this->errorHandler;
    }

    /**
     * @return Crypto
     */
    protected function getCryptoHelper()
    {
        return $this->cryptoHelper;
    }

    /**
     * @return Checkout
     */
    protected function getCheckoutHelper()
    {
        return $this->checkoutHelper;
    }

    /**
     * @return \Magento\Framework\Message\ManagerInterface
     */
    protected function getMessageManager()
    {
        return $this->messageManager;
    }

    /**
     * @return OrderRepositoryInterface
     */
    protected function getOrderRepo()
    {
        return $this->orderRepo;
    }

    /**
     * @return ApiRequest
     */
    protected function getApiHelper()
    {
        return $this->apiHelper;
    }

    /**
     * @return CartRepositoryInterface
     */
    protected function getQuoteRepository()
    {
        return $this->quoteRepository;
    }

    /**
     * @return JsonFactory
     */
    protected function getJsonResultFactory()
    {
        return $this->jsonResultFactory;
    }

    /**
     * @param $order
     * @param $transactionId
     * @throws LocalizedException
     */
    protected function invoiceOrder($order, $transactionId)
    {
        if (!$order->canInvoice()) {
            throw new LocalizedException(
                __('Cannot create an invoice.')
            );
        }

        $invoice = $this->getInvoiceService()->prepareInvoice($order);

        if (!$invoice->getTotalQty()) {
            throw new LocalizedException(
                __('You can\'t create an invoice without products.')
            );
        }

        /*
         * Look Magento/Sales/Model/Order/Invoice.register() for CAPTURE_OFFLINE explanation.
         * Basically, if !config/can_capture and config/is_gateway and CAPTURE_OFFLINE and
         * Payment.IsTransactionPending => pay (Invoice.STATE = STATE_PAID...)
         */
        $invoice->setTransactionId($transactionId);
        $invoice->setRequestedCaptureCase(Order\Invoice::CAPTURE_OFFLINE);
        $invoice->register();
        $transaction = $this->getDbTransaction()->addObject($invoice)->addObject($invoice->getOrder());
        $transaction->save();
    }

    /**
     * @return \Magento\Framework\Controller\Result\RedirectFactory
     */
    protected function getRedirectFactory()
    {
        return $this->resultRedirectFactory;
    }

    /**
     * @param Order $order
     * @param $failureReason
     * @return Order
     * @throws LocalizedException
     */
    protected function cancelOrder(Order $order, $failureReason)
    {
        $orderState = Order::STATE_CANCELED;
        if ($order->getStatus() != $orderState) {
            try {
                $message = "Order #" . $order->getIncrementId() . " was cancelled by Xendit because " . $failureReason;
                $order->setState($orderState)
                    ->setStatus($orderState)
                    ->addStatusHistoryComment($message);
                $this->orderRepo->save($order);

                $this->getCheckoutHelper()->cancelOrderById($order->getId(), "Order #" . ($order->getId()) . " was rejected by Xendit");
                $this->getCheckoutHelper()->restoreQuote(); //restore cart

                $this->logger->info($message);
            } catch (\Exception $ex) {
                $this->logger->error('Cancel order failed:' . $ex->getMessage(), ['order_id' => $order->getIncrementId()]);
                throw new LocalizedException(
                    new Phrase($ex->getMessage())
                );
            }
        }
        return $order;
    }

    /**
     * @return StoreManagerInterface
     */
    protected function getStoreManager()
    {
        return $this->storeManager;
    }

    /**
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function getXenditCallbackUrl()
    {
        $baseUrl = $this->getStoreManager()->getStore()->getBaseUrl(UrlInterface::URL_TYPE_LINK);

        return $baseUrl . 'xendit/checkout/notification';
    }

    protected function getStateMultishipping()
    {
        return $this->state;
    }

    protected function getMultishippingType()
    {
        return $this->multishipping;
    }

    /**
     * @return array|mixed
     */
    protected function getMultiShippingOrderIds()
    {
        return $this->multishipping->getOrderIds();
    }

    /**
     * @param Order $order
     * @return bool
     */
    protected function orderValidToCreateXenditInvoice(Order $order): bool
    {
        if (empty($order->getXenditTransactionId())) {
            return true;
        }
        return false;
    }

    /**
     * Get preferred payment from order
     *
     * @param Order $order
     * @return false|string
     */
    protected function getPreferredMethod(Order $order)
    {
        $payment = $order->getPayment();
        return $this->getDataHelper()->xenditPaymentMethod(
            $payment->getMethod()
        );
    }

    /**
     * @param string $orderByIncrementId
     * @return Order
     */
    public function getOrderByIncrementId(string $orderByIncrementId): Order
    {
        $order = $this->orderFactory->create();
        return $order->loadByIncrementId($orderByIncrementId);
    }
}
