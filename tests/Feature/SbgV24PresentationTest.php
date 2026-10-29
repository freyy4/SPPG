<?php

namespace Tests\Feature;

use App\Models\{User, PurchaseOrder, LoginPopupSetting};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SbgV24PresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_scoped_presentation_uses_existing_records_and_exports_isolated_fixtures(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 29)->setTime(10, 0));
        $user = User::factory()->create(['name'=>'Admin Sentra Buah', 'role'=>'super_admin', 'region'=>'Pusat']);
        $this->actingAs($user);
        LoginPopupSetting::create(['user_id'=>$user->id, 'enabled'=>true, 'title'=>'Perbaikan & Penambahan Fitur', 'message'=>'Selamat datang di Sentra Buah Garut. Perencanaan buah dan pengelolaan PO kini lebih nyaman di semua perangkat.']);
        $names = ['Semangka', 'Pisang Cavendish', 'Mangga Harum Manis', 'Alpukat Mentega', 'Apel Fuji', 'Pear Century'];
        for ($i=0; $i<32; $i++) {
            PurchaseOrder::create(['po_number'=>'PO-V24-'.$i, 'item_name'=>$names[$i%6], 'category'=>'Buah', 'quantity_sbg'=>100, 'quantity_b'=>5, 'unit'=>'Kg', 'maker_price'=>15000+$i*1000, 'supplier_price'=>10000, 'supplier_price_b'=>9000, 'partner_margin'=>1000, 'transaction_date'=>'2026-10-29', 'region'=>'Pusat', 'status_stage'=>$i%2 ? 'Selesai':'Pending', 'priority'=>'Normal', 'supplier_name'=>'UD Sari Buah']);
        }
        $base='/dashboard/fruit-calculator';
        for ($i=0; $i<16; $i++) {
            $fruit=$this->postJson($base.'/fruits', ['name'=>$names[$i%6].($i>=6 ? ' Pilihan '.$i:''), 'unit'=>'Buah', 'units_per_kg'=>5, 'portion_pb'=>1, 'portion_pk'=>0.5, 'price'=>12000+$i*1000])->assertCreated()->json();
            $this->postJson($base.'/decisions', ['request_key'=>(string) Str::uuid(), 'decision_date'=>'2026-10-29', 'budget'=>10000000, 'pb'=>100, 'pk'=>50, 'selected_id'=>$fruit['id'], 'candidates'=>[['id'=>$fruit['id'], 'version'=>$fruit['version']]]])->assertCreated();
        }
        $response=$this->withSession(['show_login_popup'=>true])->get('/dashboard')->assertOk();
        if ($directory=getenv('SBG_BROWSER_FIXTURES')) {
            if (!is_dir($directory)) mkdir($directory,0755,true);
            file_put_contents($directory.'/dashboard.html',$response->getContent());
            file_put_contents($directory.'/calculator.json',$this->getJson($base)->getContent());
            file_put_contents($directory.'/archive.json',$this->getJson($base.'/decisions')->getContent());
        }
        $this->assertDatabaseCount('purchase_orders',32);
        $this->assertDatabaseCount('fruit_catalog',16);
        $this->assertDatabaseCount('fruit_decisions',16);
    }
}
