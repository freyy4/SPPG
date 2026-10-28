<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompactPurchaseOrderWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_keeps_authoritative_totals_and_can_export_isolated_browser_fixtures(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 28)->setTime(10, 0));
        $this->actingAs(User::factory()->create(['name' => 'Super Admin', 'role' => 'super_admin', 'region' => 'Pusat']));
        $empty = $this->get(route('dashboard'))->assertOk()->assertViewHas('summary', fn ($summary) => $summary['total_po'] === 0 && $summary['total_nilai_po'] == 0);
        $orders = collect(['Semangka', 'Pisang Cavendish', 'Mangga Harum Manis', 'Alpukat Mentega', 'Apel Fuji', 'Pear Century'])->map(function ($name, $index) {
            return PurchaseOrder::create([
                'po_number' => 'PO-20261028-'.(50 + $index).'HS', 'item_name' => $name,
                'category' => 'Buah', 'quantity_sbg' => 105, 'quantity_b' => 5,
                'unit' => 'Kg', 'maker_price' => 326000, 'supplier_price' => 10000,
                'supplier_price_b' => 9000, 'partner_margin' => 2000,
                'transaction_date' => '2026-10-28', 'region' => 'Pusat',
                'status_stage' => ['Selesai', 'Proses', 'Pending'][$index % 3],
                'priority' => 'Normal', 'supplier_name' => 'UD Sari Buah',
            ]);
        });
        $response = $this->get(route('dashboard'));
        $response->assertOk()->assertViewHas('summary', fn ($summary) => $summary['total_nilai_po'] === 215160000.0 && $summary['po_pending'] === 2 && $summary['po_selesai'] === 2);

        if ($directory = getenv('SBG_BROWSER_FIXTURES')) {
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            file_put_contents($directory.'/dashboard.html', $response->getContent());
            file_put_contents($directory.'/empty.html', $empty->getContent());
            file_put_contents($directory.'/orders.json', $orders->mapWithKeys(fn ($order) => [
                $order->id => $this->getJson(route('dashboard.po.show', $order))->json(),
            ])->toJson());
            file_put_contents($directory.'/calculator.json', $this->getJson(route('fruit-calculator.index'))->getContent());
        }
    }

    public function test_detail_payment_and_deletion_keep_region_and_financial_permissions(): void
    {
        $order = PurchaseOrder::create([
            'po_number' => 'PO-SAFETY', 'item_name' => 'Semangka', 'quantity' => 10,
            'transaction_date' => '2026-10-28', 'region' => 'Garut',
        ]);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'region' => 'Bandung']));
        $this->getJson(route('dashboard.po.show', $order))->assertForbidden();
        $this->patch(route('dashboard.po.toggle-payment', $order), ['payment_type' => 'supplier'])->assertForbidden();
        $this->delete(route('dashboard.po.destroy', $order))->assertForbidden();
        $this->assertNotSoftDeleted($order);

        $this->actingAs(User::factory()->create(['role' => 'admin', 'region' => 'Garut']));
        $this->getJson(route('dashboard.po.show', $order))->assertOk()->assertJsonPath('data.id', $order->id);
        $this->patch(route('dashboard.po.toggle-payment', $order), ['payment_type' => 'supplier'])->assertForbidden();
        $this->assertFalse($order->fresh()->is_supplier_paid);

        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        foreach (['partner_fee' => 'is_partner_fee_paid', 'supplier' => 'is_supplier_paid', 'ksppg_fee' => 'is_ksppg_fee_paid'] as $type => $field) {
            $this->patch(route('dashboard.po.toggle-payment', $order), ['payment_type' => $type])->assertRedirect();
            $this->assertTrue($order->fresh()->{$field});
            $this->patch(route('dashboard.po.toggle-payment', $order), ['payment_type' => $type])->assertRedirect();
            $this->assertFalse($order->fresh()->{$field});
        }
        $this->delete(route('dashboard.po.destroy', $order))->assertRedirect();
        $this->assertSoftDeleted($order);
    }
}
