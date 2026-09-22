<?php

namespace Tests\Feature;

use App\Models\LoginPopupSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginPopupTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_superadmin_can_change_popup_settings(): void
    {
        foreach (['admin', 'kepala_dapur'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)
                ->post(route('dashboard.login-popup.update', $user), [
                    'enabled' => '1', 'title' => 'Pengumuman', 'message' => 'Halo',
                ])->assertForbidden();
            $this->post(route('dashboard.login-popup.toggle', $user), ['enabled' => '1'])->assertForbidden();
        }

        $this->assertDatabaseCount('login_popup_settings', 0);
    }

    public function test_popup_appears_once_after_login_for_a_regular_user(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        LoginPopupSetting::create(['user_id' => $user->id, 'enabled' => true, 'title' => 'Pengumuman Baru', 'message' => 'Baca ini']);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('show_login_popup', true);

        $this->get(route('dashboard'))->assertOk()->assertViewHas('showLoginPopup', true)->assertSee('Pengumuman Baru');
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Pengumuman Baru');
    }

    public function test_superadmin_can_save_and_toggle_popup(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)->post(route('dashboard.login-popup.update', $admin), [
            'title' => 'Info',
            'message' => 'Selamat datang',
        ])->assertRedirect()->assertSessionHas('popup_feedback.type', 'success');

        $setting = LoginPopupSetting::firstOrFail();
        $this->assertTrue($setting->enabled);
        $this->assertSame('Info', $setting->title);
        $this->withSession(['show_login_popup' => true])->get(route('dashboard'))
            ->assertOk()->assertSee('src="'.asset('favicon.svg').'" alt="Logo aplikasi"', false);

        $this->post(route('dashboard.login-popup.toggle', $admin), ['enabled' => '0'])->assertRedirect();
        $this->assertFalse($setting->fresh()->enabled);

        $this->post(route('dashboard.login-popup.update', $admin), [
            'title' => 'Info baru',
        ])->assertRedirect();

        $this->assertTrue($setting->fresh()->enabled);
        $this->assertSame('Info baru', $setting->fresh()->title);
    }

    public function test_popup_cannot_be_enabled_without_content(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)
            ->post(route('dashboard.login-popup.toggle', $admin), ['enabled' => '1'])
            ->assertSessionHasErrors(['enabled'], null, 'loginPopup');

        $this->assertDatabaseCount('login_popup_settings', 0);
    }

    public function test_popup_controls_are_only_visible_to_superadmin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'kepala_dapur']))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Setting Popup');

        $this->actingAs(User::factory()->create(['role' => 'super_admin']))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Daftar Daerah & Penanggung Jawab', false)
            ->assertSee('Setting Popup');
    }

    public function test_each_user_gets_only_their_own_popup(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $first = User::factory()->create(['role' => 'admin']);
        $second = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('dashboard.login-popup.update', $first), [
            'title' => 'Khusus Pengguna Pertama', 'message' => 'Halo pertama',
        ])->assertRedirect();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Popup untuk '.$first->name.' berhasil disimpan dan diaktifkan.');
        $this->get(route('dashboard'))
            ->assertDontSee('Popup untuk '.$first->name.' berhasil disimpan dan diaktifkan.');

        $this->assertTrue(LoginPopupSetting::where('user_id', $first->id)->firstOrFail()->enabled);
        $this->assertDatabaseMissing('login_popup_settings', ['user_id' => $second->id]);

        $this->actingAs($first)->withSession(['show_login_popup' => true])
            ->get(route('dashboard'))->assertSee('Khusus Pengguna Pertama');
        $this->actingAs($second)->withSession(['show_login_popup' => true])
            ->get(route('dashboard'))->assertDontSee('Khusus Pengguna Pertama');
    }

    public function test_failed_popup_save_shows_popup_feedback(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)->post(route('dashboard.login-popup.update', $admin), [
            '_popup_form' => '1',
            '_popup_user_id' => $admin->id,
            '_popup_user_name' => $admin->name,
        ])->assertSessionHasErrors(['title'], null, 'loginPopup');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('popup-feedback-title')
            ->assertSee('Isi judul atau pesan sebelum menyimpan popup.');
    }
}
