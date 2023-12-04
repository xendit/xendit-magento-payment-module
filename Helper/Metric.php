<?php

namespace Xendit\M2Invoice\Helper;

use Magento\Framework\Exception\LocalizedException;
use Xendit\M2Invoice\Model\Payment\Xendit as XenditPayment;

/**
 * Class Metric
 * @package Xendit\M2Invoice\Helper
 */
class Metric
{
    /**
     * @var XenditPayment
     */
    protected $xenditPayment;

    /**
     * @var ApiRequest
     */
    protected $apiRequestHelper;

    /**
     * @var Data
     */
    protected $dataHelper;

    /**
     * @param XenditPayment $xenditPayment
     * @param ApiRequest $apiRequestHelper
     * @param Data $dataHelper
     */
    public function __construct(
        XenditPayment $xenditPayment,
        ApiRequest $apiRequestHelper,
        Data $dataHelper
    ) {
        $this->xenditPayment = $xenditPayment;
        $this->apiRequestHelper = $apiRequestHelper;
        $this->dataHelper = $dataHelper;
    }

    /**
     * @param string $name
     * @param array $tags
     * @param string $error_code
     * @return null
     * @throws \Exception
     */
    public function sendMetric(string $name, array $tags, string $error_code = '')
    {
        $metrics = [
            'name'              => $name,
            'additional_tags'   => array_merge(
                [
                    'version' => \Xendit\M2Invoice\Helper\Data::XENDIT_M2INVOICE_VERSION,
                    'is_live' => $this->xenditPayment->isLive()
                ],
                $tags
            )
        ];
        if ($error_code) {
            $metrics['additional_tags']['error_code'] = $error_code;
        }
        return $this->trackMetricCount($metrics);
    }

    /**
     * Log metrics to Datadog for monitoring
     *
     * @param array $requestData
     * @return mixed|void
     * @throws \Exception
     */
    public function trackMetricCount(array $requestData)
    {
        try {
            return $this->apiRequestHelper->request(
                $this->dataHelper->getXenditApiUrl() . "/tpi/log/metrics/count",
                \Zend\Http\Request::METHOD_POST,
                $requestData
            );
        } catch (LocalizedException $e) {
        }
    }
}
