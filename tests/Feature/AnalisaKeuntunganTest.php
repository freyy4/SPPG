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
                ->assertJsonPath('topItem.total', 10000);
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
