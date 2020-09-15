<?php
namespace Xendit\M2Invoice\Block;

use Xendit\M2Invoice\Helper\Data;

class CustomView extends \Magento\Framework\View\Element\Html\Link
{
    private $dataHelper;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        Data $dataHelper,
        array $data = []
    ) {
        $this->dataHelper = $dataHelper;

        parent::__construct($context, $data);
    }

    public function getSubscriptionConfig()
    {
        $data['card_subscription_interval'] = $this->dataHelper->getSubscriptionInterval();
        $data['card_subscription_interval_count'] = $this->dataHelper->getSubscriptionIntervalCount();

        return $data;
    }
}
