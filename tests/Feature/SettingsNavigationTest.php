<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_uses_shared_navigation_with_authoritative_role_visibility(): void
    {
        foreach (['admin' => false, 'kepala_dapur' => true, 'super_admin' => true] as $role => $master) {
            $user = User::factory()->create(['role' => $role]);
            $response = $this->actingAs($user)->get('/settings/account');
            $response->assertOk()->assertSee('id="sbg-sidebar"', false)
                ->assertSee('aria-controls="sbg-sidebar"', false)
                ->assertSee(route('dashboard').'#data-po', false)
                ->assertSee(route('dashboard').'#katalog-buah', false)
                ->assertSee('Pengaturan Akun')->assertSee('Konfirmasi Logout')
                ->assertSee('data-sbg-logout', false);
            if ($master) $response->assertSee(route('dashboard').'#master-data', false);
            else $response->assertDontSee(route('dashboard').'#master-data', false);
        }
    }
}
