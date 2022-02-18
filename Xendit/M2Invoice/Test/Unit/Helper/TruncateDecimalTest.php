<?php
declare(strict_types=1);

namespace Xendit\M2Invoice\Test\Unit\Helper;

use Xendit\M2Invoice\Helper\Data;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;

class TruncateDecimalTest extends TestCase
{
    /**
     * @var Data
     */
    protected $_dataHelper;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $objectManager = new ObjectManager($this);
        $this->_dataHelper = $objectManager->getObject(Data::class);
    }

    /**
     * @return void
     */
    public function testTruncateDecimal()
    {
        $this->assertEquals($this->_dataHelper->truncateDecimal(100000.235), 100000);
        $this->assertEquals($this->_dataHelper->truncateDecimal(100000.59), 100000);
        $this->assertEquals($this->_dataHelper->truncateDecimal(100000.99), 100000);
    }
}
