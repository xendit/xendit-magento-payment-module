<?php
namespace Xendit\M2Invoice\Block;

use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Xendit\M2Invoice\Helper\Data;

class CustomView extends Template
{
    private $dataHelper;

    public function __construct(
        Context $context,
        Registry $registry,
        Data $dataHelper,
        array $data = []
    ) {
        $this->registry = $registry;
        $this->dataHelper = $dataHelper;

        parent::__construct($context, $data);
    }

    /**
     * Retrieve current order model instance
     *
     * @return \Magento\Sales\Model\Order
     */
    public function getOrder()
    {
        return $this->registry->registry('current_order');
    }

    public function getPaymentMethod()
    {
        return $this->getOrder()->getPayment()->getMethodInstance()->getCode();
    }

    public function getSubscriptionConfig()
    {
        $data = array();
        $data['card_subscription_interval'] = $this->dataHelper->getSubscriptionInterval();
        $data['card_subscription_interval_count'] = $this->dataHelper->getSubscriptionIntervalCount();

        return $data;
    }

    public function getInstallmentData()
    {
        return $this->getOrder()->getPayment()->getAdditionalInformation('xendit_installment');
    }
}
