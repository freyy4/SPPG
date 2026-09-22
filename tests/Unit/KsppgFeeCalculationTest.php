<?php

namespace Tests\Unit;

use App\Models\PurchaseOrder;
use PHPUnit\Framework\TestCase;

class KsppgFeeCalculationTest extends TestCase
{
    public function test_missing_supplier_prices_do_not_contribute_to_fees(): void
    {
        foreach ([null, '', 0, '0.00'] as $price) {
            $order = new PurchaseOrder([
                'quantity' => 10,
                'maker_price' => 12000,
                'partner_margin' => 1000,
                'supplier_price' => $price,
            ]);
            $this->assertEquals(0, $order->ksppg_margin_per_unit);
            $this->assertEquals(0, $order->total_ksppg_fee);
            $order->supplier_price = 8500;
            $this->assertEquals(2500, $order->ksppg_margin_per_unit);
            $this->assertEquals(25000, $order->total_ksppg_fee);
        }
    }
}
