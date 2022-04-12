<?php

namespace Xendit\M2Invoice\Test\Unit\Model\UI;

use Xendit\M2Invoice\Helper\Data;
use Xendit\M2Invoice\Model\Ui\ConfigProvider;
use Xendit\M2Invoice\Test\Unit\UnitTestCase;

class XenditConfigProviderTest extends UnitTestCase
{
    public function testGetConfig()
    {
        $xenditHelperMock = $this->createMock(Data::class);

        /** @var ConfigProvider $instance */
        $instance = $this->objectManager->getObject(ConfigProvider::class, [
            'xenditHelper' => $xenditHelperMock,
        ]);

        $result = $instance->getConfig();

        $this->assertArrayHasKey('xendit', $result['payment']);
        $this->assertArrayHasKey('cc', $result['payment']);
        $this->assertArrayHasKey('cc_subscription', $result['payment']);
        $this->assertArrayHasKey('bcava', $result['payment']);
        $this->assertArrayHasKey('bniva', $result['payment']);
        $this->assertArrayHasKey('bjbva', $result['payment']);
        $this->assertArrayHasKey('briva', $result['payment']);
        $this->assertArrayHasKey('bsiva', $result['payment']);
        $this->assertArrayHasKey('mandiriva', $result['payment']);
        $this->assertArrayHasKey('permatava', $result['payment']);
        $this->assertArrayHasKey('alfamart', $result['payment']);
        $this->assertArrayHasKey('ovo', $result['payment']);
        $this->assertArrayHasKey('dana', $result['payment']);
        $this->assertArrayHasKey('linkaja', $result['payment']);
        $this->assertArrayHasKey('shopeepay', $result['payment']);
        $this->assertArrayHasKey('shopeepayph', $result['payment']);
        $this->assertArrayHasKey('indomaret', $result['payment']);
        $this->assertArrayHasKey('dd_bri', $result['payment']);
        $this->assertArrayHasKey('kredivo', $result['payment']);
        $this->assertArrayHasKey('gcash', $result['payment']);
        $this->assertArrayHasKey('grabpay', $result['payment']);
        $this->assertArrayHasKey('paymaya', $result['payment']);
        $this->assertArrayHasKey('dd_bpi', $result['payment']);
        $this->assertArrayHasKey('seven_eleven', $result['payment']);
        $this->assertArrayHasKey('dd_ubp', $result['payment']);
        $this->assertArrayHasKey('billease', $result['payment']);
        $this->assertArrayHasKey('cebuana', $result['payment']);
        $this->assertArrayHasKey('dp_palawan', $result['payment']);
        $this->assertArrayHasKey('dp_mlhuillier', $result['payment']);
        $this->assertArrayHasKey('dp_ecpay_loan', $result['payment']);
    }
}
