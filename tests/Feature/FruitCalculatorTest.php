<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FruitCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private string $base = '/dashboard/fruit-calculator';

    private function login(string $region = 'Garut', string $role = 'admin'): User
    {
        $user = User::factory()->create(compact('role', 'region'));
        $this->actingAs($user);

        return $user;
    }

    private function fields(array $values = []): array
    {
        return array_merge(['name' => 'Anggur', 'unit' => 'Butir', 'units_per_kg' => 2.5,
            'portion_pb' => 1.5, 'portion_pk' => 0.5, 'price' => 10000], $values);
    }

    public function test_bulk_paste_validates_all_fruits_and_respects_region_access(): void
    {
        $this->login();
        $this->postJson($this->base.'/fruits/bulk', ['fruits' => [$this->fields(), $this->fields(['price' => 0])]])->assertUnprocessable();
        $this->assertDatabaseCount('fruit_catalog', 0);
        $this->postJson($this->base.'/fruits/bulk?region=Pusat', ['fruits' => [$this->fields()]])->assertForbidden();
        $this->postJson($this->base.'/fruits/bulk', ['fruits' => [$this->fields(['id' => 999, 'region' => 'Pusat']), $this->fields(['name' => 'Apel'])]])->assertCreated()->assertJson(['count' => 2]);
        $this->assertDatabaseCount('fruit_catalog', 2);
        $this->assertDatabaseCount('fruit_catalog_prices', 2);
        $this->assertDatabaseHas('fruit_catalog', ['name' => 'Anggur', 'region' => 'Garut']);
        $this->assertDatabaseMissing('fruit_catalog', ['region' => 'Pusat']);
    }

    private function createFruit(): array
    {
        return $this->postJson($this->base.'/fruits', $this->fields())->assertCreated()->json();
    }

    private function decision(array $fruit, array $values = []): array
    {
        return array_merge(['request_key' => (string) Str::uuid(), 'decision_date' => today()->toDateString(),
            'budget' => 14000, 'pb' => 2, 'pk' => 1, 'selected_id' => $fruit['id'],
            'candidates' => [['id' => $fruit['id'], 'version' => $fruit['version']]]], $values);
    }

    public function test_catalog_is_independent_of_purchase_orders_and_shared_across_sessions(): void
    {
        $this->login();
        $po = PurchaseOrder::create(['po_number' => 'PO-TEST', 'item_name' => 'Apel', 'category' => 'Buah',
            'quantity' => 10, 'unit' => 'Kg', 'maker_price' => 30000, 'region' => 'Garut', 'transaction_date' => today()]);
        $this->getJson($this->base)->assertOk()->assertJsonCount(0, 'fruits');
        $fruit = $this->createFruit();
        $po->update(['maker_price' => 99999]);
        $po->delete();
        $this->flushSession();
        $this->login();
        $this->getJson($this->base)->assertOk()->assertJsonCount(1, 'fruits')
            ->assertJsonPath('fruits.0.name', 'Anggur')->assertJsonPath('fruits.0.price', 10000)
            ->assertJsonPath('fruits.0.source_po_id', null);
        $this->putJson($this->base.'/fruits/'.$fruit['id'], $this->fields(['version' => $fruit['version'], 'name' => 'Jeruk']))->assertOk();
        $this->assertDatabaseHas('fruit_catalog', ['id' => $fruit['id'], 'name' => 'Jeruk']);
    }

    public function test_calculation_archive_idempotency_and_stale_catalog_protection(): void
    {
        $this->login();
        $fruit = $this->createFruit();
        $payload = $this->decision($fruit);
        $id = $this->postJson($this->base.'/decisions', $payload)->assertCreated()
            ->assertJsonPath('snapshot.selected.cost', 14000)->assertJsonPath('snapshot.selected.kg', 1.4)->json('id');
        $this->postJson($this->base.'/decisions', $payload)->assertCreated()->assertJsonPath('id', $id);
        $this->assertDatabaseCount('fruit_decisions', 1);
        $this->postJson($this->base.'/decisions', $this->decision($fruit, ['budget' => 13999.99]))->assertUnprocessable();
        $this->putJson($this->base.'/fruits/'.$fruit['id'], $this->fields(['version' => $fruit['version'], 'price' => 20000]))->assertOk();
        $this->putJson($this->base.'/fruits/'.$fruit['id'], $this->fields(['version' => $fruit['version']]))->assertConflict();
        $this->postJson($this->base.'/decisions', $this->decision($fruit))->assertConflict();
        $this->getJson($this->base.'/decisions/'.$id)->assertOk()->assertJsonPath('snapshot.selected.price', 10000);
    }

    public function test_regional_access_and_input_validation(): void
    {
        $this->login();
        foreach (['name' => '', 'price' => 0, 'units_per_kg' => 0, 'portion_pb' => -1, 'unit' => 'invalid'] as $key => $value) {
            $this->postJson($this->base.'/fruits', $this->fields([$key => $value]))->assertUnprocessable();
        }
        $fruit = $this->createFruit();
        $id = $this->postJson($this->base.'/decisions', $this->decision($fruit))->assertCreated()->json('id');
        $this->postJson($this->base.'/decisions', $this->decision($fruit, ['pb' => 0, 'pk' => 0]))->assertUnprocessable();
        $this->login('Bandung');
        $this->getJson($this->base)->assertOk()->assertJsonCount(0, 'fruits');
        $this->getJson($this->base.'?region=Garut')->assertForbidden();
        $this->postJson($this->base.'/fruits?region=Garut', $this->fields())->assertForbidden();
        $this->putJson($this->base.'/fruits/'.$fruit['id'], $this->fields(['version' => $fruit['version']]))->assertNotFound();
        $this->getJson($this->base.'/fruits/'.$fruit['id'].'/history?days=7')->assertNotFound();
        $this->getJson($this->base.'/decisions/'.$id)->assertNotFound();
        $this->postJson($this->base.'/decisions', $this->decision($fruit))->assertConflict();
        $this->login('Garut', 'supplier');
        $this->getJson($this->base)->assertForbidden();
        $this->postJson($this->base.'/fruits', $this->fields())->assertForbidden();
    }

    public function test_history_tracks_catalog_prices_and_average(): void
    {
        $this->login();
        $this->travel(-2)->days();
        $fruit = $this->createFruit();
        $this->travelBack();
        $this->getJson($this->base)->assertJsonPath('fruits.0.previous_week_average', 10000);
        $this->putJson($this->base.'/fruits/'.$fruit['id'], $this->fields(['version' => $fruit['version'], 'price' => 12000]))->assertOk();
        $this->getJson($this->base.'/fruits/'.$fruit['id'].'/history?days=7')->assertOk()->assertJsonCount(2);
        $fresh = $this->getJson($this->base)->json('fruits.0');
        $this->putJson($this->base.'/fruits/'.$fruit['id'], $this->fields(['version' => $fresh['version'], 'price' => 12000, 'name' => 'Anggur Hijau']))->assertOk();
        $this->assertDatabaseCount('fruit_catalog_prices', 2);
        $this->getJson($this->base.'/fruits/'.$fruit['id'].'/history?days=8')->assertUnprocessable();
    }

    public function test_superadmin_can_view_all_but_must_choose_region_to_create(): void
    {
        $this->login();
        $fruit = $this->createFruit();
        $this->login('Pusat', 'super_admin');
        $all = '?region=Semua%20Wilayah';
        $this->getJson($this->base.$all)->assertOk()->assertJsonCount(1, 'fruits');
        $this->postJson($this->base.'/fruits'.$all, $this->fields())->assertUnprocessable();
        $this->putJson($this->base.'/fruits/'.$fruit['id'].$all, $this->fields(['version' => $fruit['version']]))->assertOk();
        $this->postJson($this->base.'/fruits?region=Pusat', $this->fields())->assertCreated();
        $this->getJson($this->base.$all)->assertJsonCount(2, 'fruits');
    }
}
