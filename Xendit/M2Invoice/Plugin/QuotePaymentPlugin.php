<?php

namespace Xendit\M2Invoice\Plugin;

use Magento\Quote\Model\Quote\Payment;

class QuotePaymentPlugin
{
    /**
     * @var array
     */
    protected $additionalInformationList = [
        'token_id',
        'masked_card_number',
        'cc_cid'
    ];

    /**
     * @param Payment $subject
     * @param array $data
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function beforeImportData(Payment $subject, array $data)
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
