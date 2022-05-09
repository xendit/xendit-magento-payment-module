<?php

namespace Xendit\M2Invoice\Test\Unit\Block;

use PHPUnit\Framework\TestCase;
use Xendit\M2Invoice\Block\Info;

/**
 * Class TestInfo
 * @package Xendit\M2Invoice\Test\Unit\Block
 */
class TestInfo extends TestCase
{
    /**
     * @var Info
     */
    protected $info;

    /**
     * TestInfo constructor.
     * @param Info $info
     */
    public function __construct(
        Info $info
    ) {
        $this->info = $info;
    }

    /**
     *
     */
    protected function setUp()
    {
        $this->info;
    }

    /**
     *
     */
    public function testGetLabel()
    {
        $label = "Something";
        $result = $this->info->getLabel($label);
        $this->assertEquals($label, $result);
    }
}
