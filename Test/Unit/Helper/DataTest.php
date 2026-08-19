<?php
declare(strict_types=1);

namespace Xendit\M2Invoice\Test\Unit\Helper;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;
use Xendit\M2Invoice\Helper\Data;

class DataTest extends TestCase
{
    /**
     * @var Data
     */
    protected $_dataHelper;

    /**
     * @var ObjectManager $objectManager
     */
    protected $objectManager;

    /**
     * @var \Magento\Directory\Model\PriceCurrency|(\Magento\Directory\Model\PriceCurrency&object&\PHPUnit\Framework\MockObject\MockObject)|(\Magento\Directory\Model\PriceCurrency&\PHPUnit\Framework\MockObject\MockObject)|(object&\PHPUnit\Framework\MockObject\MockObject)|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $priceCurrencyMock;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->_dataHelper = $this->objectManager->create(Data::class);

        $this->priceCurrencyMock = $this->getMockForAbstractClass(
            PriceCurrencyInterface::class,
            [],
            '',
            false,
            false,
            true,
            ['round']
        );
    }

    /**
     * @return void
     */
    public function testTruncateDecimal()
    {
        $this->assertEquals(100000, $this->_dataHelper->truncateDecimal(100000.235));
        $this->assertEquals(100000, $this->_dataHelper->truncateDecimal(100000.59));
        $this->assertEquals(100000, $this->_dataHelper->truncateDecimal(100000.99));
    }
}
