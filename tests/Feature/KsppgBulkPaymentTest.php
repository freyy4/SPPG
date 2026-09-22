<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KsppgBulkPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_superadmin_can_pay_all_partner_fees(): void
    {
        foreach (['admin', 'kepala_dapur'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->post(route('dashboard.ksppg-fee.pay-all'), ['ids' => [1]])
                ->assertForbidden();
        }
    }

    public function test_payment_only_settles_submitted_orders_and_is_repeatable(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);
        $orders = collect(['PO-1', 'PO-2'])->map(fn ($number) => PurchaseOrder::create([
            'po_number' => $number,
            'item_name' => 'Beras',
            'quantity' => 10,
            'supplier_price' => 8500,
            'transaction_date' => '2026-09-12',
        ]));

        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($user)->post(route('dashboard.ksppg-fee.pay-all'), [
                'ids' => [$orders[0]->id],
            ])->assertRedirect()->assertSessionHas('success');
        }

        $this->assertTrue($orders[0]->fresh()->is_ksppg_fee_paid);
        $this->assertFalse($orders[1]->fresh()->is_ksppg_fee_paid);
        $this->assertFalse($orders[0]->fresh()->is_supplier_paid);
        $this->assertFalse($orders[0]->fresh()->is_partner_fee_paid);
    }

    public function test_orders_without_supplier_price_are_not_settled(): void
    {
        $order = PurchaseOrder::create([
            'po_number' => 'PO-EMPTY',
            'item_name' => 'Beras',
            'quantity' => 10,
            'maker_price' => 10000,
            'transaction_date' => '2026-09-12',
        ]);

        $this->actingAs(User::factory()->create(['role' => 'super_admin']))
            ->post(route('dashboard.ksppg-fee.pay-all'), ['ids' => [$order->id]])
            ->assertRedirect();

        $this->assertFalse($order->fresh()->is_ksppg_fee_paid);
        $this->assertEquals(0, $order->fresh()->total_ksppg_fee);
    }
}
