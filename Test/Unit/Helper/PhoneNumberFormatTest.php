<?php
declare(strict_types=1);

namespace Xendit\M2Invoice\Test\Unit\Helper;

use Xendit\M2Invoice\Helper\PhoneNumberFormat;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;

class PhoneNumberFormatTest extends TestCase
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $objectManager = new ObjectManager($this);
        $this->helper = $objectManager->getObject(PhoneNumberFormat::class);
    }

    /**
     * @return void
     */
    public function testFormatPhoneNumber()
    {
        $this->assertEquals('+629876543219', $this->helper->formatNumber('9876543219', 'ID'));
        $this->assertEquals('+6219876543219', $this->helper->formatNumber('019876543219', 'ID'));
        $this->assertEquals('+62123456789', $this->helper->formatNumber('62-123-456-789', 'ID'));
        $this->assertEquals('+6319876543219', $this->helper->formatNumber('198 765 432 19', 'PH'));
        $this->assertEquals('+6319876543219', $this->helper->formatNumber('198.765.432-19', 'PH'));
        $this->assertEquals('+6319876543219', $this->helper->formatNumber('+63198.765.432-19', 'PH'));
        $this->assertEquals('+6319876543219', $this->helper->formatNumber('63198.765.432-19 ', 'PH'));

        // in case country code is empty, we should respond the same phone number
        $this->assertEquals('019876543219', $this->helper->formatNumber('019876543219', ''));
        $this->assertEquals('', $this->helper->formatNumber('', ''));
    }
}
