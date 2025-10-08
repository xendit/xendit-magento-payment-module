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

        $this->assertArrayHasKey('unified', $result['payment']);
    }
}
