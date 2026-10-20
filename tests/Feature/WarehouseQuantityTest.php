<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseQuantityTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        $admin = User::factory()->create(['role' => 'admin', 'region' => 'Pusat']);
        return [
            'item_name' => 'Beras', 'category' => 'Karbo', 'unit' => 'Kg',
            'transaction_date' => '2026-09-27', 'created_by' => $admin->id,
            'priority' => 'Normal', 'status_stage' => 'Cari Supplier',
            'quantity_sbg' => 10.5, 'quantity_b' => 2.25, 'quantity' => 999,
            'maker_price' => '10.000', 'partner_margin' => '1.000',
        ];
    }

    public function test_store_update_and_dashboard_use_both_warehouses(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $payload = $this->payload();
        $this->post(route('dashboard.po.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $po = PurchaseOrder::firstOrFail();
        $this->assertEquals(12.75, $po->quantity);
        $this->assertEquals(33000, $po->total_partner_fee);
        $this->assertEquals(127500, $po->quantity * $po->maker_price);
        $this->get(route('dashboard'))->assertOk()->assertViewHas('summary', fn ($summary) =>
            $summary['total_laba_mitra'] == 33000 && $summary['total_nilai_po'] == 127500);
        foreach ([[0, 4, 40000], [3, 0, 3000]] as [$sbg, $b, $fee]) {
            $payload['quantity_sbg'] = $sbg;
            $payload['quantity_b'] = $b;
            $this->put(route('dashboard.po.update', $po), $payload)->assertRedirect()->assertSessionHasNoErrors();
            $po->refresh();
            $this->assertEquals($sbg + $b, $po->quantity);
            $this->assertEquals($fee, $po->total_partner_fee);
        }
    }

    public function test_invalid_quantities_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $payload = $this->payload();
        foreach ([[-1, 2, 'quantity_sbg'], [1, -2, 'quantity_b'], [0, 0, 'quantity'], ['abc', 1, 'quantity_sbg'], [1.234, 1, 'quantity_sbg'], [1, null, 'quantity_b']] as [$sbg, $b, $error]) {
            $this->post(route('dashboard.po.store'), array_replace($payload, ['quantity_sbg' => $sbg, 'quantity_b' => $b]))
                ->assertSessionHasErrors($error);
        }
        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_historical_orders_keep_original_fee_and_can_be_split(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $payload = $this->payload();
        $po = PurchaseOrder::create([
            'po_number' => 'OLD-1', 'item_name' => 'Beras', 'quantity' => 10,
            'transaction_date' => '2026-09-27', 'maker_price' => 10000, 'partner_margin' => 1000,
        ]);
        $this->assertEquals(10000, $po->fresh()->total_partner_fee);
        $this->put(route('dashboard.po.update', $po), $payload)->assertSessionHasNoErrors();
        $this->assertEquals(33000, $po->fresh()->total_partner_fee);
    }
    public function test_supplier_card_uses_only_sbg_and_payment_status(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $payload = array_replace($this->payload(), [
            'supplier_price' => '6.000', 'supplier_price_b' => '7.500,50',
        ]);
        $this->post(route('dashboard.po.store'), $payload)->assertSessionHasNoErrors();
        $po = PurchaseOrder::firstOrFail();
        $this->assertEquals(7500.50, $po->supplier_price_b);
        $this->assertEquals(31500, $po->total_ksppg_fee);
        $this->getJson(route('dashboard.analisa', ['filter' => 'all']))->assertOk()
            ->assertJsonPath('topItem.total', 31500);
        $this->assertEquals(33000, $po->total_partner_fee);
        $this->assertEquals(127500, $po->quantity * $po->maker_price);
        $this->assertEquals(63000, $po->total_supplier_amount);
        $this->get(route('dashboard'))->assertOk()->assertViewHas('summary', fn ($summary) =>
            $summary['total_bayar_supplier'] == 63000);
        $this->get(route('dashboard.po.show', $po))->assertOk()
            ->assertJsonPath('data.total_supplier_amount', 63000);

        foreach ([[0, 4, '8.000', 0], [3, 0, '8.000', 18000], [2, 3, '0', 12000]] as [$sbg, $b, $price, $total]) {
            $payload = array_replace($payload, ['quantity_sbg' => $sbg, 'quantity_b' => $b, 'supplier_price_b' => $price]);
            $this->put(route('dashboard.po.update', $po), $payload)->assertSessionHasNoErrors();
            $this->assertEquals($total, $po->fresh()->total_supplier_amount);
            $this->assertEquals($sbg * 3000, $po->fresh()->total_ksppg_fee);
        }
        $this->post(route('dashboard.supplier.pay-all'), ['ids' => [$po->id]])->assertSessionHasNoErrors();
        $this->get(route('dashboard'))->assertOk()->assertViewHas('summary', fn ($summary) =>
            $summary['total_bayar_supplier'] == 0);
    }

    public function test_legacy_supplier_price_and_restricted_admin_edits(): void
    {
        $payload = $this->payload();
        $admin = User::findOrFail($payload['created_by']);
        $po = PurchaseOrder::create([
            'po_number' => 'SUPPLIER-OLD', 'item_name' => 'Beras', 'quantity_sbg' => 3,
            'quantity_b' => 2, 'transaction_date' => '2026-09-27',
            'supplier_price' => 6000, 'region' => 'Pusat',
        ]);
        $this->assertEquals(18000, $po->fresh()->total_supplier_amount);
        $po->update(['supplier_price_b' => 8000]);
        $this->actingAs($admin)->put(route('dashboard.po.update', $po), array_replace($payload, [
            'supplier_price' => '1', 'supplier_price_b' => '2',
        ]))->assertSessionHasNoErrors();
        $this->assertEquals(6000, $po->fresh()->supplier_price);
        $this->assertEquals(8000, $po->fresh()->supplier_price_b);

        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        foreach (['-1', 'abc'] as $invalid) {
            $this->post(route('dashboard.po.store'), array_replace($payload, ['supplier_price_b' => $invalid]))
                ->assertSessionHasErrors('supplier_price_b');
        }
    }
}
