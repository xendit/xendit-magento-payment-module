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

    /**
     * Create mock order
     *
     * @param int $entityId
     * @param $totalDue
     * @param $subTotal
     * @param $totalPaid
     * @param $shippingAmount
     * @param $taxAmount
     * @param $discountAmount
     * @return Order
     */
    protected function createMockOrder(int $entityId, $totalDue, $subTotal, $totalPaid, $shippingAmount, $taxAmount, $discountAmount): Order
    {
        $helper = new ObjectManager($this);

        /** @var Order $order */
        $order = $helper->getObject(Order::class, ['priceCurrency' => $this->priceCurrencyMock]);

        $order->setEntityId($entityId);
        $order->setSubtotal($subTotal);
        $order->setGrandTotal($totalDue);
        $order->setTotalPaid($totalPaid);
        $order->setShippingAmount($shippingAmount);
        $order->setTaxAmount($taxAmount);
        $order->setDiscountAmount($discountAmount);

        return $order;
    }

    /**
     * Test extract order fees with 3 items: Shipping, Tax, Discount
     *
     * @return void
     */
    public function testExtractOrderFeesWithThreeItems()
    {
        $totalDue = 50000;
        $order = $this->createMockOrder(999, $totalDue, 25000, 0, 20000, 10000, -5000);

        $this->priceCurrencyMock->expects($this->any())->method('round')->with($totalDue)->willReturnArgument(0);
        $this->assertEquals($totalDue, $order->getTotalDue());

        $orderFees = $this->_dataHelper->extractOrderFees($order);
        $this->assertEquals(
            [
                [
                    'type' => 'Discount',
                    'value' => -5000
                ],
                [
                    'type' => 'Shipping fee',
                    'value' => 20000
                ],
                [
                    'type' => 'Tax fee',
                    'value' => 10000
                ]
            ],
            $orderFees
        );
    }

    /**
     * Test extract order fees with 2 items: Shipping, Tax
     *
     * @return void
     */
    public function testExtractOrderFeesWithTwoItems()
    {
        $totalDue = 50000;
        $order = $this->createMockOrder(999, $totalDue, 25000, 0, 20000, 10000, 0);

        $this->priceCurrencyMock->expects($this->any())->method('round')->with($totalDue)->willReturnArgument(0);
        $this->assertEquals($totalDue, $order->getTotalDue());

        $orderFees = $this->_dataHelper->extractOrderFees($order);
        $this->assertEquals(
            [
                [
                    'type' => 'Shipping fee',
                    'value' => 20000
                ],
                [
                    'type' => 'Tax fee',
                    'value' => 10000
                ]
            ],
            $orderFees
        );
    }

    /**
     * Test extract empty order fees
     *
     * @return void
     */
    public function testExtractOrderFeesWithEmptyItems()
    {
        $totalDue = 50000;
        $order = $this->createMockOrder(999, $totalDue, 50000, 0, 0, 0, 0);

        $this->priceCurrencyMock->expects($this->any())->method('round')->with($totalDue)->willReturnArgument(0);
        $this->assertEquals($totalDue, $order->getTotalDue());

        $orderFees = $this->_dataHelper->extractOrderFees($order);
        $this->assertEquals(
            [],
            $orderFees
        );
    }

    /**
     * Test extract order other fees
     * In case the Total > Subtotal and Shipping = Tax = Discount = 0
     *
     * @return void
     */
    public function testExtractOrderFeesWithOtherFees()
    {
        $totalDue = 50000;
        $order = $this->createMockOrder(999, $totalDue, 20000, 0, 0, 0, 0);

        $this->priceCurrencyMock->expects($this->any())->method('round')->with($totalDue)->willReturnArgument(0);
        $this->assertEquals($totalDue, $order->getTotalDue());

        $orderFees = $this->_dataHelper->extractOrderFees($order);
        $this->assertEquals(
            [
                [
                    'type' => 'Other Fees',
                    'value' => 30000
                ]
            ],
            $orderFees
        );
    }

    /**
     * Should return fee object which has: Shipping, Tax, Discount
     *
     * @return void
     */
    public function testMergeFeesObjectShouldReturnFeeObjectForFirstOrder()
    {
        $totalDue = 50000;
        $firstOrder = $this->createMockOrder(999, $totalDue, 25000, 0, 20000, 10000, -5000);
        $secondOrder = $this->createMockOrder(1000, $totalDue, 50000, 0, 0, 0, 0);

        $this->priceCurrencyMock->expects($this->any())->method('round')->with($totalDue)->willReturnArgument(0);
        $this->assertEquals($totalDue, $firstOrder->getTotalDue());
        $this->assertEquals($totalDue, $secondOrder->getTotalDue());

        $orderFees[] = $this->_dataHelper->extractOrderFees($firstOrder);
        $orderFees[] = $this->_dataHelper->extractOrderFees($secondOrder);

        $finalOrderFees = $this->_dataHelper->mergeFeesObject($orderFees);
        $this->assertEquals(
            [
                [
                    'type' => 'Discount',
                    'value' => -5000
                ],
                [
                    'type' => 'Shipping fee',
                    'value' => 20000
                ],
                [
                    'type' => 'Tax fee',
                    'value' => 10000
                ]
            ],
            $finalOrderFees
        );
    }

    /**
     * Should return fee object which has: Shipping, Tax, Discount
     *
     * @return void
     */
    public function testMergeFeesObjectShouldReturnFeeObject()
    {
        $totalDue = 50000;
        $firstOrder = $this->createMockOrder(999, $totalDue, 25000, 0, 20000, 10000, -5000);
        $secondOrder = $this->createMockOrder(1000, $totalDue, 38000, 0, 10000, 10000, -2000);

        $this->priceCurrencyMock->expects($this->any())->method('round')->with($totalDue)->willReturnArgument(0);
        $this->assertEquals($totalDue, $firstOrder->getTotalDue());
        $this->assertEquals($totalDue, $secondOrder->getTotalDue());

        $orderFees[] = $this->_dataHelper->extractOrderFees($firstOrder);
        $orderFees[] = $this->_dataHelper->extractOrderFees($secondOrder);

        $finalOrderFees = $this->_dataHelper->mergeFeesObject($orderFees);
        $this->assertEquals(
            [
                [
                    'type' => 'Discount',
                    'value' => -7000
                ],
                [
                    'type' => 'Shipping fee',
                    'value' => 30000
                ],
                [
                    'type' => 'Tax fee',
                    'value' => 20000
                ]
            ],
            $finalOrderFees
        );
    }

    /**
     * Should return empty array because has no fees for first order & second order
     *
     * @return void
     */
    public function testMergeFeesObjectShouldReturnEmptyArray()
    {
        $totalDue = $subTotal = 50000; // Total = SubTotal => Order has no fees
        $firstOrder = $this->createMockOrder(999, $totalDue, $subTotal, 0, 0, 0, 0);
        $secondOrder = $this->createMockOrder(1000, $totalDue, $subTotal, 0, 0, 0, 0);

        $this->priceCurrencyMock->expects($this->any())->method('round')->with($totalDue)->willReturnArgument(0);
        $this->assertEquals($totalDue, $firstOrder->getTotalDue());
        $this->assertEquals($totalDue, $secondOrder->getTotalDue());

        $orderFees[] = $this->_dataHelper->extractOrderFees($firstOrder);
        $orderFees[] = $this->_dataHelper->extractOrderFees($secondOrder);

        $finalOrderFees = $this->_dataHelper->mergeFeesObject($orderFees);
        $this->assertEquals(
            [],
            $finalOrderFees
        );
    }

    /**
     * Test extract order other fees for first_order + second_order
     * In case the Total > Subtotal and Shipping = Tax = Discount = 0
     *
     * @return void
     */
    public function testMergeFeesObjectShouldReturnOtherFees()
    {
        $totalDue = 50000;
        $firstOrder = $this->createMockOrder(999, $totalDue, 20000, 0, 0, 0, 0);
        $secondOrder = $this->createMockOrder(1000, $totalDue, 20000, 0, 0, 0, 0);

        $this->priceCurrencyMock->expects($this->any())->method('round')->with($totalDue)->willReturnArgument(0);
        $this->assertEquals($totalDue, $firstOrder->getTotalDue());
        $this->assertEquals($totalDue, $secondOrder->getTotalDue());

        $orderFees[] = $this->_dataHelper->extractOrderFees($firstOrder);
        $orderFees[] = $this->_dataHelper->extractOrderFees($secondOrder);

        $finalOrderFees = $this->_dataHelper->mergeFeesObject($orderFees);
        $this->assertEquals(
            [
                [
                    'type' => 'Other Fees',
                    'value' => 60000 // Total fees of first_order + second_order
                ]
            ],
            $finalOrderFees
        );
    }
}
