<?php

namespace Xendit\M2Invoice\Plugin;

use Psr\Log\LoggerInterface;

class QuotePaymentPlugin
{
    private $logger;

    /**
     * @var array
     */
    protected $additionalInformationList = [
        'token_id',
        'masked_card_number',
        'cc_cid'
    ];

    public function __construct(
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
    }

    /**
     * @param \Magento\Quote\Model\Quote\Payment $subject
     * @param array $data
     * @return array
     */
    public function beforeImportData(\Magento\Quote\Model\Quote\Payment $subject, array $data)
    {
        if (array_key_exists('additional_data', $data)) {
            $additionalData = $data['additional_data'];

            foreach ($this->additionalInformationList as $additionalInformationKey) {
                if (isset($additionalData[$additionalInformationKey])) {
                    $subject->setAdditionalInformation(
                        $additionalInformationKey,
                        $additionalData[$additionalInformationKey]
                    );
                }
            }
        }

        return [$data];
    }
}