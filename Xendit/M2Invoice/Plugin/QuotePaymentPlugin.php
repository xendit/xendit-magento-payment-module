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
        'masked_card_number'
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
            // $this->logger->info('Masuk beforeImportData additional_data' . print_r($data, true));
            $additionalData = $data['additional_data'];
            // $additionalDataToBeSaved = [];

            if (isset($additionalData['token_id'])) {
                $this->logger->info('Akan ke save' . print_r($additionalData, true));
                $subject->setAdditionalInformation(
                    'token_id',
                    $additionalData['token_id']
                );
            }

            // foreach ($this->additionalInformationList as $additionalInformationKey) {
            //     if (isset($additionalData[$additionalInformationKey])) {
            //         $additionalDataToBeSaved[$additionalInformationKey] = $additionalData[$additionalInformationKey];
            //     }
            // }
            // $this->logger->info('Akan ke save' . print_r($additionalDataToBeSaved, true));

            // if (!empty($additionalDataToBeSaved)) {
            //     $subject->setAdditionalData(
            //         json_encode($additionalDataToBeSaved)
            //     );
            // }
        }

        return [$data];
    }
}