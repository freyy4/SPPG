<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardDateFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_date_filters_include_boundaries_and_update_summary_and_persist(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        foreach (['2026-09-01', '2026-09-02', '2026-09-03'] as $date) {
            PurchaseOrder::create([
                'po_number' => 'PO-'.$date,
                'item_name' => 'Beras',
                'quantity' => 2,
                'maker_price' => 10000,
                'transaction_date' => $date,
            ]);
        }

        foreach ([
            [['date_from' => '2026-09-02', 'date_to' => '2026-09-02'], 1],
            [['date_from' => '2026-09-02'], 2],
            [['date_to' => '2026-09-02'], 2],
        ] as [$filter, $count]) {
            $this->post(route('dashboard'), $filter)->assertOk()
                ->assertViewHas('purchaseOrders', fn ($orders) => $orders->total() === $count)
                ->assertViewHas('summary', fn ($summary) => $summary['total_po'] === $count && $summary['total_nilai_po'] == $count * 20000);
            $this->get(route('dashboard'))->assertOk()
                ->assertViewHas('purchaseOrders', fn ($orders) => $orders->total() === $count);
        }

        $this->get(route('dashboard.reset'))->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk()
            ->assertViewHas('purchaseOrders', fn ($orders) => $orders->total() === 3);
    }

    public function test_invalid_dates_do_not_replace_active_filters(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        foreach ([
            ['date_from' => 'invalid'],
            ['date_from' => '2026-02-30'],
            ['date_from' => '2026-09-03', 'date_to' => '2026-09-01'],
        ] as $filter) {
            $this->withSession(['filter_date_from' => '2026-09-01'])
                ->from(route('dashboard'))->post(route('dashboard'), $filter)
                ->assertRedirect(route('dashboard'))->assertSessionHasErrors()
                ->assertSessionHas('filter_date_from', '2026-09-01');
        }
    }
}
