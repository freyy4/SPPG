<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AnalisaKeuntunganTest extends TestCase
{
    use RefreshDatabase;

    public function test_analysis_uses_model_profit_formula_and_date_range(): void
    {
        Carbon::setTestNow('2026-09-16 12:00:00');

        try {
            $user = User::factory()->create(['role' => 'super_admin']);
            $this->order('PO-1', 'Beras', 'Pangan', '2026-09-14', 10, 15000, 8000, 2000);
            $this->order('PO-2', 'Beras', 'Pangan', '2026-09-15', 2, 20000, 10000, 3000);
            $this->order('PO-3', 'Telur', 'Protein', '2026-09-16', 4, 15000, 0, 2000);
            $this->order('PO-OLD', 'Gula', 'Pangan', '2026-09-07', 10, 20000, 1000, 0);
            $this->order('PO-FUTURE', 'Minyak', 'Pangan', '2026-09-17', 10, 20000, 1000, 0);

            $this->actingAs($user)->getJson(route('dashboard.analisa', ['filter' => 'week']))
                ->assertOk()
                ->assertJsonPath('topItem.name', 'Beras')
                ->assertJsonPath('topItem.total', 64000)
                ->assertJsonPath('topCategory.total', 64000)
                ->assertJsonPath('worstItem.name', 'Telur')
                ->assertJsonPath('worstItem.total', 0);

            $this->actingAs($user)->getJson(route('dashboard.analisa', ['filter' => 'invalid']))
                ->assertUnprocessable();
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_analysis_respects_user_region(): void
    {
        Carbon::setTestNow('2026-09-16 12:00:00');

        try {
            $own = User::factory()->create(['role' => 'admin', 'region' => 'Garut']);
            $this->order('PO-GARUT', 'Beras', 'Pangan', '2026-09-16', 2, 15000, 8000, 2000);
            PurchaseOrder::where('po_number', 'PO-GARUT')->update(['region' => 'Garut']);
            $this->order('PO-LAIN', 'Telur', 'Protein', '2026-09-16', 10, 15000, 8000, 2000);
            PurchaseOrder::where('po_number', 'PO-LAIN')->update(['region' => 'Bandung']);

            $this->actingAs($own)->getJson(route('dashboard.analisa', ['filter' => 'all']))
                ->assertOk()
                ->assertJsonPath('topItem.name', 'Beras')
                ->assertJsonPath('topItem.total', 4000);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_analysis_returns_three_ranked_items_and_categories(): void
    {
        Carbon::setTestNow('2026-09-16 12:00:00');

        try {
            $user = User::factory()->create(['role' => 'super_admin']);
            foreach ([
                ['PO-1', 'Beras', 'Pangan', 5000],
                ['PO-2', 'Telur', 'Protein', 3000],
                ['PO-3', 'Wortel', 'Sayur', 2000],
                ['PO-4', 'Apel', 'Buah', 1000],
            ] as [$number, $item, $category, $profit]) {
                $this->order($number, $item, $category, '2026-09-16', 1, $profit + 1000, 1000, 0);
            }

            $this->actingAs($user)->getJson(route('dashboard.analisa', ['filter' => 'week']))
                ->assertOk()
                ->assertJsonCount(3, 'topItems')
                ->assertJsonCount(3, 'topCategories')
                ->assertJsonPath('topItems.0.name', 'Beras')
                ->assertJsonPath('topItems.1.name', 'Telur')
                ->assertJsonPath('topItems.2.name', 'Wortel')
                ->assertJsonPath('topCategories.0.name', 'Pangan')
                ->assertJsonPath('topCategories.1.name', 'Protein')
                ->assertJsonPath('topCategories.2.name', 'Sayur');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_analysis_uses_role_fee_and_its_payment_status(): void
    {
        PurchaseOrder::create([
            'po_number' => 'ROLE-FEE', 'item_name' => 'Buah', 'category' => 'Buah',
            'transaction_date' => today(), 'quantity_sbg' => 2, 'quantity_b' => 3,
            'maker_price' => 10000, 'supplier_price' => 6000, 'partner_margin' => 1000,
            'region' => 'Garut',
        ]);
        foreach (['admin' => 32000, 'super_admin' => 6000, 'kepala_dapur' => 6000] as $role => $expected) {
            $user = User::factory()->create(['role' => $role, 'region' => 'Garut']);
            $this->actingAs($user)->getJson(route('dashboard.analisa', ['filter' => 'all']))
                ->assertOk()->assertJsonPath('topItem.total', $expected)
                ->assertJsonPath('topCategory.total', $expected)
                ->assertJsonPath('worstItem.total', $expected);
        }
        PurchaseOrder::where('po_number', 'ROLE-FEE')->update(['is_partner_fee_paid' => true]);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'region' => 'Garut']))
            ->getJson(route('dashboard.analisa', ['filter' => 'all']))->assertOk()->assertJsonCount(0, 'topItems');
        $this->actingAs(User::factory()->create(['role' => 'kepala_dapur', 'region' => 'Garut']))
            ->getJson(route('dashboard.analisa', ['filter' => 'all']))->assertOk()->assertJsonPath('topItem.total', 6000);
        PurchaseOrder::where('po_number', 'ROLE-FEE')->update(['is_ksppg_fee_paid' => true]);
        $this->getJson(route('dashboard.analisa', ['filter' => 'all']))->assertOk()->assertJsonCount(0, 'topItems');
    }

    public function test_po_analysis_is_only_available_to_super_admin(): void
    {
        foreach (['admin', 'kepala_dapur'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->getJson(route('dashboard.po-analysis'))->assertForbidden();
        }
    }

    public function test_po_analysis_weights_prices_and_keeps_units_separate(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        foreach ([['A', '2025-01-10', 10, 100], ['B', '2025-01-20', 30, 200], ['C', '2025-02-10', 5, 350], ['D', '2025-04-10', 5, 100]] as [$number, $date, $qty, $price]) {
            PurchaseOrder::create([
                'po_number' => $number, 'item_name' => 'Semangka', 'category' => 'Buah', 'unit' => 'Kg',
                'transaction_date' => $date, 'quantity_sbg' => $qty, 'quantity_b' => 2,
                'supplier_price' => $price, 'supplier_price_b' => 500, 'maker_price' => 1000, 'region' => 'Garut',
            ]);
        }
        PurchaseOrder::create([
            'po_number' => 'OTHER-UNIT', 'item_name' => 'Semangka', 'category' => 'Buah', 'unit' => 'Pcs',
            'transaction_date' => '2025-01-10', 'quantity' => 1, 'supplier_price' => 999, 'region' => 'Garut',
        ]);
        $url = route('dashboard.po-analysis', ['year' => 2025, 'category' => 'Buah', 'region' => 'Garut']);
        $this->getJson($url)->assertOk()->assertJsonCount(2, 'rows')
            ->assertJsonPath('rows.0.unit', 'Kg')
            ->assertJsonPath('rows.0.months.0.average', 175)
            ->assertJsonPath('rows.0.months.0.quantity', 40)
            ->assertJsonPath('rows.0.months.1.change', 100)
            ->assertJsonPath('rows.0.months.2.average', null)
            ->assertJsonPath('rows.0.months.3.change', null)
            ->assertJsonPath('rows.0.lowest_months', [4]);
        $this->getJson($url.'&source=supplier_b')->assertOk()->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.months.0.average', 500)->assertJsonPath('excluded', 1);
        $this->getJson($url.'&source=maker')->assertOk()->assertJsonPath('rows.0.months.0.average', 1000);
        $this->getJson($url.'&month=2')->assertOk()->assertJsonCount(1, 'rows');
        $this->getJson($url.'&month=3')->assertOk()->assertJsonCount(0, 'rows');
        $this->getJson($url.'&item=TidakAda')->assertOk()->assertJsonCount(0, 'rows');
        $this->getJson(route('dashboard.po-analysis', ['year' => 2025, 'category' => 'Sayur']))->assertOk()->assertJsonCount(0, 'rows');
        $this->getJson(route('dashboard.po-analysis', ['year' => 2025, 'region' => 'Bandung']))->assertOk()->assertJsonCount(0, 'rows');
        $this->getJson(route('dashboard.po-analysis', ['source' => 'invalid']))->assertUnprocessable();
        $this->getJson(route('dashboard.po-analysis', ['month' => 13]))->assertUnprocessable();
    }

    private function order(string $number, string $item, string $category, string $date, float $quantity, float $maker, float $supplier, float $partner): void
    {
        PurchaseOrder::create([
            'po_number' => $number,
            'item_name' => $item,
            'category' => $category,
            'transaction_date' => $date,
            'quantity' => $quantity,
            'maker_price' => $maker,
            'supplier_price' => $supplier,
            'partner_margin' => $partner,
        ]);
    }
}
