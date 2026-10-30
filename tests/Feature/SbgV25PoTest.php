<?php
namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SbgV25PoTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        $admin = User::factory()->create(['role' => 'admin', 'region' => 'Pusat']);
        return ['item_name' => 'Cavendish sintetis', 'category' => 'Buah', 'priority' => 'Normal',
            'transaction_date' => '2026-10-29', 'created_by' => $admin->id, 'status_stage' => 'Cari Supplier',
            'quantity_sbg' => 10.5, 'quantity_b' => 2.25, 'unit' => 'Kg', 'supplier_name' => 'Supplier sintetis',
            'supplier_price' => '6.000', 'supplier_price_b' => '7.500,50', 'maker_price' => '10.000',
            'partner_margin' => '1.000', 'notes' => 'Catatan asli'];
    }

    public function test_shared_add_edit_sections_keep_original_fields_and_known_pricing(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $payload = $this->payload();
        $this->post(route('dashboard.po.store'), $payload)->assertSessionHasNoErrors();
        $po = PurchaseOrder::firstOrFail();
        $id = $po->id;
        $createdTotals = [$po->quantity, $po->total_partner_fee, $po->total_supplier_amount, $po->total_ksppg_fee, $po->quantity * $po->maker_price, $po->supplier_price_b];
        $this->assertEquals([12.75, 33000, 63000, 31500, 127500, 7500.5], $createdTotals);
        foreach (['Sortir', 'Pengiriman', 'Selesai', 'Retur', 'Kekurangan', 'Perubahan Item', 'Cari Supplier'] as $status) {
            $this->put(route('dashboard.po.update', $po), array_replace($payload, ['status_stage' => $status]))->assertSessionHasNoErrors();
            $po->refresh();
            $this->assertSame($id, $po->id);
            $this->assertSame($status, $po->status_stage);
            $this->assertEquals([12.75, 33000, 63000, 31500, 127500, 7500.5], [$po->quantity, $po->total_partner_fee, $po->total_supplier_amount, $po->total_ksppg_fee, $po->quantity * $po->maker_price, $po->supplier_price_b]);
        }
        $this->assertDatabaseCount('purchase_orders', 1);
        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertSame(8, substr_count($html, 'data-po-section='));
        foreach (['create', 'edit'] as $mode) {
            $this->assertStringContainsString('data-po-mode="'.$mode.'"', $html);
        }
        $this->assertStringContainsString('sbg-v25-po.js', $html);
        $this->assertStringContainsString('sbg-v25-po.css', $html);
    }

    public function test_real_edit_prepopulation_and_role_specific_fields_and_denial(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);
        $payload = $this->payload();
        $this->actingAs($super)->post(route('dashboard.po.store'), $payload)->assertSessionHasNoErrors();
        $po = PurchaseOrder::firstOrFail();
        $json = $this->getJson(route('dashboard.po.show', $po))->assertOk();
        foreach (['item_name','category','priority','created_by','quantity_sbg','quantity_b','unit','supplier_name','notes','status_stage'] as $field) {
            $json->assertJsonPath('data.'.$field, $payload[$field]);
        }
        $json->assertJsonPath('data.supplier_price_b', 7500.5)->assertJsonPath('data.maker_price', 10000);
        $this->assertStringStartsWith('2026-10-29', $json->json('data.transaction_date'));
        $this->assertNotEmpty($json->json('data.updated_at'));
        foreach (['super_admin','kepala_dapur','admin'] as $role) {
            $user = User::factory()->create(['role' => $role, 'region' => 'Pusat']);
            $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();
            preg_match_all('/<form[^>]*data-po-form.*?<\/form>/s', $html, $forms);
            $this->assertCount(2, $forms[0]);
            foreach ($forms[0] as $form) {
                foreach (['transaction_date','category','priority','item_name','quantity_sbg','quantity_b','unit','maker_price','partner_margin','status_stage','notes'] as $field) $this->assertStringContainsString('name="'.$field.'"', $form);
                foreach (['created_by','supplier_name','supplier_price','supplier_price_b'] as $field) {
                    if ($role === 'admin') $this->assertStringNotContainsString('name="'.$field.'"', $form);
                    else $this->assertStringContainsString('name="'.$field.'"', $form);
                }
            }
        }
        $admin = User::findOrFail($payload['created_by']);
        $this->actingAs($admin)->put(route('dashboard.po.update', $po), array_replace($payload, ['supplier_price' => '999.999', 'supplier_price_b' => '999.999', 'supplier_name' => 'Injected']))->assertSessionHasNoErrors();
        $this->assertEquals([6000,7500.5,'Supplier sintetis'], [$po->refresh()->supplier_price,$po->supplier_price_b,$po->supplier_name]);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'region' => 'Luar']))->getJson(route('dashboard.po.show', $po))->assertForbidden();
        $this->putJson(route('dashboard.po.update', $po), $payload)->assertForbidden();
    }

    public function test_missing_input_and_invalid_status_do_not_mutate_and_legacy_error_reopens_edit(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $payload = $this->payload();
        $this->post(route('dashboard.po.store'), $payload)->assertSessionHasNoErrors();
        $po = PurchaseOrder::firstOrFail();
        $bad = $payload;
        unset($bad['quantity_b']);
        $this->putJson(route('dashboard.po.update', $po), $bad)->assertUnprocessable()->assertJsonValidationErrors('quantity_b');
        $this->putJson(route('dashboard.po.update', $po), array_replace($payload, ['status_stage' => 'Draft']))->assertUnprocessable()->assertJsonValidationErrors('status_stage');
        $this->putJson(route('dashboard.po.update', $po), array_replace($payload, ['quantity_sbg'=>0,'quantity_b'=>0]))->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->assertSame('Cari Supplier', $po->refresh()->status_stage);
        $this->assertEquals(12.75, $po->quantity);
        $this->from(route('dashboard'))->put(route('dashboard.po.update', $po), array_replace($payload, ['_sbg_edit' => $po->id, 'item_name'=>'', 'notes'=>'Catatan jangan hilang']))->assertRedirect(route('dashboard'))->assertSessionHasErrors('item_name');
        $this->withCookie(config('session.cookie'), session()->getId())->get(route('dashboard'))->assertOk()->assertSee('isEditModalOpen: true', false)->assertSee('Catatan jangan hilang')->assertSee('data-server-errors', false);
        $this->assertDatabaseCount('purchase_orders', 1);
    }

    public function test_stale_version_returns_409_and_preserves_newer_record_legacy_updates_still_work(): void
    {
        $this->travelTo(now()->startOfSecond());
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $payload = $this->payload();
        $this->post(route('dashboard.po.store'), $payload)->assertSessionHasNoErrors();
        $po = PurchaseOrder::firstOrFail();
        $version = $po->updated_at->toISOString();
        $this->putJson(route('dashboard.po.update', $po), array_replace($payload, ['updated_at' => $version, 'notes' => 'Pembaruan pertama']))->assertOk()->assertJsonPath('success', true);
        $this->putJson(route('dashboard.po.update', $po), array_replace($payload, ['updated_at' => $version, 'notes' => 'Stale overwrite']))->assertStatus(409);
        $this->assertSame('Pembaruan pertama', $po->refresh()->notes);
        $this->put(route('dashboard.po.update', $po), array_replace($payload, ['notes' => 'Legacy']))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Legacy', $po->refresh()->notes);
        $this->putJson(route('dashboard.po.update', $po), array_replace($payload, ['updated_at' => $version, 'notes' => 'Stale after legacy']))->assertStatus(409);
        $this->assertSame('Legacy', $po->refresh()->notes);
        $this->putJson(route('dashboard.po.update', $po), array_replace($payload, ['updated_at' => 'not-a-date']))->assertUnprocessable()->assertJsonValidationErrors('updated_at');
    }

    public function test_shared_client_submission_preserves_errors_conflicts_and_decimal_input(): void
    {
        $script = <<<'JS'
const assert = require('node:assert/strict');
global.formatCurrencyValue = value => value === '' || value == null ? '' : Number(value).toLocaleString('id-ID', { maximumFractionDigits: 2 });
require('node:vm').runInThisContext(require('node:fs').readFileSync('public/js/sbg-v25-po.js', 'utf8'));
(async () => {
const form = sbgPoForm('edit');
form.$nextTick = fn => fn();
form.$el = {querySelector: () => ({focus(){}})};
form.load({id:7});
form.draft.notes = 'Ne pas perdre';
const field = {value:'7500,50'};
assert.equal(form.formatPriceInput(field), '7.500,50');
const native = {checkValidity: () => true, querySelectorAll: () => [], elements:{quantity_sbg:{value:'10'},quantity_b:{value:'2'}}};
global.FormData = class {};
global.fetch = async () => ({status:422, ok:false, json:async () => ({errors:{item_name:['Wajib diisi'], quantity:['Jumlah wajib positif']}})});
await form.submit({target:native});
assert.deepEqual(form.errors.item_name, ['Wajib diisi']);
assert.deepEqual(form.errors.quantity_sbg, ['Jumlah wajib positif']);
assert.equal(form.draft.notes, 'Ne pas perdre');
assert.equal(form.busy, false);
global.fetch = async () => ({status:409, ok:false, json:async () => ({message:'Konflik versi'})});
await form.submit({target:native});
assert.equal(form.message, 'Konflik versi');
assert.equal(form.draft.notes, 'Ne pas perdre');
assert.equal(form.busy, false);
let submitted = false;
form.mode = 'create';
global.HTMLFormElement = {prototype:{submit(){submitted=true;}}};
await form.submit({target:native});
assert.equal(submitted, true);
assert.equal(form.busy, true);
})().catch(e => {console.error(e); process.exit(1);});
JS;
        $process = new \Symfony\Component\Process\Process(['node', '-e', $script], base_path());
        $process->setTimeout(10)->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }

    public function test_shared_client_dirty_close_and_decimal_prepopulation(): void
    {
        $script = <<<'JS'
const assert = require('node:assert/strict');
global.formatCurrencyValue = value => value === '' || value == null ? '' : Number(value).toLocaleString('id-ID', { maximumFractionDigits: 2 });
require('node:vm').runInThisContext(require('node:fs').readFileSync('public/js/sbg-v25-po.js', 'utf8'));
const form = sbgPoForm('edit');
let legacyMarkerRemoved = false;
form.$el = {querySelector: () => ({removeAttribute(name){legacyMarkerRemoved = name === 'data-sbg-create-form';}})};
form.$watch = () => {};
form.init();
assert.equal(legacyMarkerRemoved, true);
const restored = sbgPoForm('create', {item_name:'Tetap', maker_price:'10000'}, {quantity:['Jumlah wajib positif']});
restored.$el = form.$el;
restored.init();
assert.deepEqual(restored.errors.quantity_sbg, ['Jumlah wajib positif']);
assert.ok(restored.message);
assert.equal(restored.draft.item_name, 'Tetap');
assert.equal(restored.draft.maker_price, '10.000');
form.isEditModalOpen = true;
form.load({id: 7, item_name:'Pisang', quantity:12.75, quantity_sbg:10.5, quantity_b:2.25, supplier_price_b:7500.5, maker_price:10000, transaction_date:'2026-10-29T00:00:00.000000Z'});
assert.equal(form.draft.supplier_price_b, '7.500,5');
assert.equal(form.draft.transaction_date, '2026-10-29');
const legacy = sbgPoForm('edit');
legacy.load({quantity: 8, supplier_price: 6000, supplier_price_b: null});
assert.equal(legacy.draft.quantity_sbg, 8);
assert.equal(legacy.draft.quantity_b, 0);
assert.equal(legacy.draft.supplier_price_b, '6.000');
assert.equal(form.warehouseValue('b'), 22500);
assert.equal(form.dirty(), false);
form.draft.item_name = 'Pisang baru';
assert.equal(form.dirty(), true);
let prompts = 0;
global.confirm = () => { prompts++; return false; };
form.requestClose();
assert.equal(form.isEditModalOpen, true);
assert.equal(form.draft.item_name, 'Pisang baru');
assert.equal(prompts, 1);
global.confirm = () => true;
let focusReturned = false;
form.$nextTick = fn => fn();
form.poEditReturnFocus = {isConnected:true, getClientRects:()=>[{}], focus(){focusReturned=true;}};
form.requestClose();
assert.equal(form.isEditModalOpen, false);
assert.equal(focusReturned, true);
JS;
        $process = new \Symfony\Component\Process\Process(['node', '-e', $script], base_path());
        $process->setTimeout(10)->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }
}
