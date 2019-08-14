<?php

namespace Xendit\M2Invoice\Test\Unit\Block;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;

class TestInfo extends TestCase
{
    protected $objectManager;
    protected $info;

    protected function setUp()
    {
        $this->objectManager = new ObjectManager($this);
        $this->info = $this->objectManager->getObject("Xendit\M2Invoice\Block\Info");
    }

    public function testGetLabel()
    {
        $label = "Something";
        $result = $this->info->getLabel($label);
        $this->assertEquals($label, $result);
    }
}