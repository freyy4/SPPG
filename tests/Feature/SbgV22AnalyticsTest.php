<?php
namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SbgV22AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_total_insight_uses_complete_existing_role_fee_query_not_top_three(): void
    {
        Carbon::setTestNow('2026-10-28 12:00:00');
        try {
            $admin = User::factory()->create(['role'=>'admin','region'=>'Garut']);
            $super = User::factory()->create(['role'=>'super_admin','region'=>'Garut']);
            foreach ([['A', '2026-10-28', 1], ['B','2026-10-27',2], ['C','2026-10-26',3], ['D','2026-10-28',4], ['OLD','2026-09-01',5]] as [$name,$date,$qty]) {
                PurchaseOrder::create(['po_number'=>$name,'item_name'=>$name,'category'=>'Buah','transaction_date'=>$date,'quantity_sbg'=>$qty,'quantity_b'=>2,'maker_price'=>10000,'supplier_price'=>6000,'partner_margin'=>1000,'region'=>'Garut']);
            }
            $this->actingAs($super)->getJson(route('dashboard.analisa',['filter'=>'week']))->assertOk()->assertJsonCount(3,'topItems')->assertJsonPath('totalProfit',30000);
            $this->actingAs($super)->getJson(route('dashboard.analisa',['filter'=>'all']))->assertOk()->assertJsonPath('totalProfit',45000);
            $this->actingAs($admin)->getJson(route('dashboard.analisa',['filter'=>'week']))->assertOk()->assertJsonPath('totalProfit',90000);
            PurchaseOrder::query()->update(['is_partner_fee_paid'=>true]);
            $this->getJson(route('dashboard.analisa',['filter'=>'week']))->assertOk()->assertJsonPath('totalProfit',0);
            $this->actingAs($super)->withSession(['filter_region'=>'Bandung'])->getJson(route('dashboard.analisa',['filter'=>'week']))->assertOk()->assertJsonPath('totalProfit',0);
        } finally { Carbon::setTestNow(); }
    }
}
