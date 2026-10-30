<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SbgV25ExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_profile_preserves_managed_identity_and_posts_real_forms(): void
    {
        $user = User::factory()->create(['name' => 'Ayu Fixture', 'email' => 'ayu@example.test', 'role' => 'admin', 'region' => 'Garut']);
        $this->actingAs($user);
        $html = view('settings.account', ['user' => $user, 'errors' => new \Illuminate\Support\ViewErrorBag])->render();
        foreach (['data-sbg-settings', '/settings/account/profile', '/settings/account/photo', 'multipart/form-data', 'name="_token"', 'name="_method" value="PATCH"', 'name="phone"', 'aria-readonly="true"', '?tab=security', 'Ayu Fixture'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        foreach (['name="email"', 'name="role"', 'name="region"', 'delete-user'] as $text) {
            $this->assertStringNotContainsString($text, $html);
        }
    }

    public function test_security_forms_use_server_contracts_and_never_prefill_secrets(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        request()->query->set('tab', 'security');
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        session()->flash('_old_input', ['current_password' => 'MUST-NOT-RENDER', 'pin' => '123456', 'code' => '123456']);
        $html = view('settings.account', ['user'=>$user, 'errors'=>new \Illuminate\Support\ViewErrorBag, 'securityState'=>['pin_enabled'=>true, 'totp_enabled'=>true, 'recovery_codes_remaining'=>3], 'sessionManagementSupported'=>false])->render();
        foreach (['/account/password', '/settings/account/pin', '/settings/account/totp', '/settings/account/recovery-codes', 'name="current_pin"', 'data-pin-input', 'name="current_password"', 'name="recovery_code"', 'Belum didukung', '3 kode tersisa'] as $text) $this->assertStringContainsString($text,$html);
        $this->assertStringNotContainsString('MUST-NOT-RENDER', $html);
        $this->assertStringNotContainsString('value="123456"', $html);
        $this->assertStringNotContainsString('name="trusted_device"', $html);
    }

    public function test_challenge_posts_otp_recovery_and_cancel_to_distinct_real_endpoints(): void
    {
        $html = view('auth.two-factor-challenge', ['errors'=>new \Illuminate\Support\ViewErrorBag])->render();
        foreach (['data-sbg-challenge', '/two-factor-challenge', '/two-factor-challenge/cancel', 'name="code"', 'name="recovery_code"', 'name="_token"', 'autocomplete="one-time-code"'] as $text) $this->assertStringContainsString($text,$html);
        $this->assertStringNotContainsString('name="password"',$html);
    }

    public function test_legacy_stylesheets_consume_semantic_theme_tokens(): void
    {
        foreach (['sbg-v2.css','sbg-v23.css','sbg-v24.css','fruit-calculator.css','sbg-v24-receipt.css'] as $file) {
            $css = file_get_contents(public_path('css/'.$file));
            $this->assertStringContainsString('var(--legacy-', $css, $file);
        }
        $this->assertStringContainsString('sbg-theme:', view('components.sbg.theme-head')->render());
    }

    public function test_backend_settings_route_renders_actual_setup_payload_and_profile_validation(): void
    {
        $user = User::factory()->create(['password'=>\Illuminate\Support\Facades\Hash::make('Synthetic-Only!42')]);
        $this->actingAs($user)->get('/settings/account?tab=security')->assertOk()->assertSee('Mulai Pengaturan 2FA');
        $this->post('/settings/account/totp/setup', ['current_password'=>'Synthetic-Only!42'])->assertSessionHasNoErrors()->assertRedirect();
        $this->get('/settings/account?tab=security')->assertOk()->assertSee('data-totp-setup',false)->assertSee('data:image/',false)->assertSee('kunci manual');
        $this->from('/settings/account?tab=profile')->patch('/settings/account/profile',['name'=>'','phone'=>'not-a-phone'])->assertSessionHasErrors(['name','phone']);
        $this->assertNotSame('', $user->fresh()->name);
    }

    public function test_synthetic_schema_has_real_creator_and_dashboard_identity_before_any_screenshot(): void
    {
        $user = User::factory()->create(['role'=>'super_admin','region'=>'Pusat']);
        $order = \App\Models\PurchaseOrder::create(['created_by'=>$user->id,'po_number'=>'PO-SYNTHETIC-PREFLIGHT','item_name'=>'Pisang Fixture','category'=>'Buah','quantity_sbg'=>28,'quantity_b'=>0,'unit'=>'Kg','maker_price'=>25000,'supplier_price'=>20000,'supplier_price_b'=>0,'partner_margin'=>2500,'transaction_date'=>'2026-10-29','region'=>'Pusat','status_stage'=>'Cari Supplier','priority'=>'Normal','supplier_name'=>'Supplier Fixture']);
        $this->assertSame($user->id, $order->created_by);
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('x-data="dashboardApp"',false)->assertSee('PO-SYNTHETIC-PREFLIGHT')->assertSee('sbg-v25-theme.css')->assertSee('/settings/account?tab=profile',false);
    }

    public function test_supported_sessions_and_once_only_recovery_use_only_supplied_safe_state(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        request()->query->set('tab','security');
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        $data = ['user'=>$user,'securityState'=>['pin_enabled'=>false,'totp_enabled'=>true,'recovery_codes_remaining'=>1],'sessionManagementSupported'=>true,'activeSessions'=>[['id'=>'PRIVATE-SESSION-ID','user_agent'=>'Synthetic browser','ip_address'=>'192.0.2.5','last_active_at'=>'2026-10-30T00:00:00+00:00','is_current'=>true]],'recoveryCodes'=>['SYNTHETIC-ONCE-CODE']];
        $html = view('settings.account',$data)->render();
        foreach(['Sesi saat ini','Synthetic browser','192.0.2.5','/settings/account/sessions','data-recovery-once','SYNTHETIC-ONCE-CODE'] as $text) $this->assertStringContainsString($text,$html);
        $this->assertStringNotContainsString('PRIVATE-SESSION-ID',$html);
        $data['recoveryCodes']=[];
        $this->assertStringNotContainsString('data-recovery-once',view('settings.account',$data)->render());
    }

    public function test_login_uses_separate_accessible_viewport_layers_and_real_auth_form(): void
    {
        $this->get('/login')->assertOk()->assertSee('data-sbg-login', false)
            ->assertSee('sbg-v25-theme.css')->assertSee('data-password-toggle', false)
            ->assertSee('Lupa password?')->assertSee('name="remember"', false)
            ->assertSee('name="_token"', false)->assertSee('data-submit-loading', false);
    }
}
