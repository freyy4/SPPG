<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SbgV22PresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_create_has_accessible_success_toast_without_new_mutation_contract(): void
    {
        $user = User::factory()->create(['role'=>'super_admin']);
        $this->actingAs($user)->withSession(['success'=>'Purchase Order berhasil dibuat.'])
            ->get(route('dashboard'))->assertOk()->assertSee('data-sbg-po-success',false)->assertSee('Lihat daftar PO');
    }

    public function test_create_redirect_reopens_form_with_server_errors_and_original_input(): void
    {
        $user = User::factory()->create(['role'=>'super_admin']);
        $response = $this->actingAs($user)->from(route('dashboard'))->post(route('dashboard.po.store'), [
            '_sbg_create'=>'1', 'item_name'=>'Nama tetap sintetis', 'quantity_sbg'=>0, 'quantity_b'=>0,
        ]);
        $response->assertRedirect(route('dashboard'))->assertSessionHasErrors('quantity');
        // Carry the actual browser session ID across the two isolated requests.
        // JSON session serialization otherwise remarshal a previous in-process bag.
        $this->withCookie(config('session.cookie'), session()->getId())->get(route('dashboard'))->assertOk()->assertSee('Nama tetap sintetis')
            ->assertSee('isCreateModalOpen: true', false)->assertSee('data-server-errors',false);
        $this->assertDatabaseCount('purchase_orders',0);
    }

    public function test_native_create_still_persists_original_payload_and_closes_with_success_toast(): void
    {
        $super = User::factory()->create(['role'=>'super_admin']);
        $admin = User::factory()->create(['role'=>'admin','region'=>'Pusat']);
        $this->actingAs($super)->from(route('dashboard'))->post(route('dashboard.po.store'), [
            '_sbg_create'=>'1', 'item_name'=>'Semangka sintetis', 'category'=>'Buah', 'unit'=>'Kg',
            'transaction_date'=>'2026-10-28','created_by'=>$admin->id,'priority'=>'Normal','status_stage'=>'Cari Supplier',
            'quantity_sbg'=>10.5,'quantity_b'=>2.25,'supplier_price'=>'6.000','supplier_price_b'=>'7.000',
            'maker_price'=>'10.000','partner_margin'=>'1.000','supplier_name'=>'Supplier sintetis','notes'=>'Tidak nyata',
        ])->assertRedirect(route('dashboard'))->assertSessionHasNoErrors();
        $order = \App\Models\PurchaseOrder::firstOrFail();
        $this->assertEquals(12.75,$order->quantity);
        $this->assertEquals(33000,$order->total_partner_fee);
        $this->assertEquals(63000,$order->total_supplier_amount);
        $this->assertEquals(31500,$order->total_ksppg_fee);
        $this->get(route('dashboard'))->assertOk()->assertSee('data-sbg-po-success',false)
            ->assertSee('isCreateModalOpen: false',false)->assertSee('Lihat daftar PO');
    }

    public function test_original_role_specific_create_fields_remain_guarded(): void
    {
        foreach (['super_admin','kepala_dapur','admin'] as $role) {
            $html = $this->actingAs(User::factory()->create(['role'=>$role,'region'=>'Pusat']))->get(route('dashboard'))->assertOk()->getContent();
            preg_match('/<form[^>]*data-sbg-create-form.*?<\/form>/s',$html,$form);
            foreach (['transaction_date','category','priority','item_name','quantity_sbg','quantity_b','unit','maker_price','partner_margin','status_stage','notes'] as $field) {
                $this->assertStringContainsString('name="'.$field.'"',$form[0]);
            }
            foreach (['created_by','supplier_name','supplier_price','supplier_price_b'] as $field) {
                if ($role === 'admin') $this->assertStringNotContainsString('name="'.$field.'"',$form[0]);
                else $this->assertStringContainsString('name="'.$field.'"',$form[0]);
            }
        }
    }
}
