<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use OTPHP\TOTP;
use Psr\Clock\ClockInterface;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_uses_existing_identity_and_rejects_privileged_fields(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'region' => 'A']);
        $this->actingAs($user)->patchJson('/settings/account/profile', ['name' => 'New Name', 'phone' => '+6281234'])->assertOk();
        $this->assertSame('New Name', $user->fresh()->name);
        $this->assertSame('+6281234', $user->fresh()->phone);
        $this->patchJson('/settings/account/profile', ['name' => 'Other', 'role' => 'super_admin', 'email' => 'other@example.test', 'region' => 'B'])->assertUnprocessable();
        $this->assertSame('admin', $user->fresh()->role);
        $this->assertSame('A', $user->fresh()->region);
        $this->assertSame(1, User::count());
    }

    public function test_pin_lifecycle_hashing_and_account_lockout(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/settings/account/pin', ['current_password' => 'password', 'pin' => '123456', 'pin_confirmation' => '123456'])->assertOk();
        $this->assertTrue(Hash::check('123456', $user->fresh()->pin_hash));
        $this->assertArrayNotHasKey('pin_hash', $user->fresh()->toArray());
        $this->postJson('/settings/account/pin/verify', ['pin' => '123456'])->assertOk();
        $this->putJson('/settings/account/pin', ['current_password' => 'password', 'current_pin' => '123456', 'pin' => '654321', 'pin_confirmation' => '654321'])->assertOk();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/settings/account/pin/verify', ['pin' => '111111'])->assertUnprocessable();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.22'])->postJson('/settings/account/pin/verify', ['pin' => '654321'])->assertStatus(429);
        $this->travel(301)->seconds();
        $this->deleteJson('/settings/account/pin', ['current_password' => 'password', 'current_pin' => '654321'])->assertOk();
        $this->assertNull($user->fresh()->pin_hash);
    }

    private function otp(string $secret): TOTP
    {
        return TOTP::createFromSecret($secret, new class implements ClockInterface
        {
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable;
            }
        });
    }

    public function test_totp_setup_requires_verification_encrypts_secret_and_returns_local_qr(): void
    {
        $user = User::factory()->create();
        $setup = $this->actingAs($user)->postJson('/settings/account/totp/setup', ['current_password' => 'password'])->assertOk()->json('setup');
        $this->assertStringStartsWith('otpauth://totp/', $setup['provisioning_uri']);
        $this->assertStringStartsWith('data:image/png;base64,', $setup['qr_data_uri']);
        $this->assertNotFalse(getimagesizefromstring(base64_decode(explode(',', $setup['qr_data_uri'])[1])));
        $this->assertNull($user->fresh()->totp_enabled_at);
        $this->assertNotSame($setup['manual_key'], DB::table('users')->where('id', $user->id)->value('totp_pending_secret'));
        $this->postJson('/settings/account/totp/confirm', ['code' => 'bad'])->assertUnprocessable();
        $codes = $this->postJson('/settings/account/totp/confirm', ['code' => $this->otp($setup['manual_key'])->at(now()->timestamp)])->assertOk()->json('recoveryCodes');
        $this->assertCount(8, $codes);
        $this->assertNotNull($user->fresh()->totp_enabled_at);
        $this->assertNull($user->fresh()->totp_pending_secret);
        $this->assertTrue(Hash::check($codes[0], $user->fresh()->recovery_code_hashes[0]));
        $this->postJson('/settings/account/totp/setup', ['current_password' => 'password'])->assertStatus(409);
    }

    private function enroll(User $user): array
    {
        $setup = $this->actingAs($user)->postJson('/settings/account/totp/setup', ['current_password' => 'password'])->assertOk()->json('setup');
        $codes = $this->postJson('/settings/account/totp/confirm', ['code' => $this->otp($setup['manual_key'])->at(now()->timestamp)])->assertOk()->json('recoveryCodes');

        return [$setup['manual_key'], $codes];
    }

    public function test_real_login_waits_for_second_factor_and_rejects_replayed_totp_and_recovery(): void
    {
        $user = User::factory()->create();
        [$secret,$codes] = $this->enroll($user);
        $this->post('/logout');
        $login = $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => true])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
        $this->assertNotContains(Auth::guard()->getRecallerName(), array_map(fn ($cookie) => $cookie->getName(), $login->headers->getCookies()));
        $this->getJson('/settings/account')->assertUnauthorized();
        $this->getJson('/dashboard')->assertUnauthorized();
        $this->postJson('/two-factor-challenge', ['code' => $this->otp($secret)->at(now()->timestamp)])->assertUnprocessable();
        $this->assertGuest();
        $this->travel(31)->seconds();
        $this->post('/two-factor-challenge', ['code' => $this->otp($secret)->at(now()->timestamp)])->assertRedirect('/dashboard')->assertCookie(Auth::guard()->getRecallerName());
        $this->assertFalse(session()->has('security.pending_login'));
        $this->assertAuthenticatedAs($user);
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/two-factor-challenge');
        $this->post('/two-factor-challenge', ['recovery_code' => $codes[0]])->assertRedirect('/dashboard');
        $this->assertCount(7, $user->fresh()->recovery_code_hashes);
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->postJson('/two-factor-challenge', ['recovery_code' => $codes[0]])->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_settings_are_read_only_safe_and_never_expose_enabled_secret(): void
    {
        $user = User::factory()->create();
        $data = $this->actingAs($user)->getJson('/settings/account')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json();
        $this->assertFalse($data['securityState']['totp_enabled']);
        $this->assertNull($data['securityState']['setup']);
        $this->assertNull($user->fresh()->totp_pending_secret);
        [$secret,$codes] = $this->enroll($user);
        $data = $this->getJson('/settings/account')->assertOk()->json();
        $this->assertTrue($data['securityState']['totp_enabled']);
        $this->assertSame(8, $data['securityState']['recovery_codes_remaining']);
        $this->assertNull($data['securityState']['setup']);
        $this->assertStringNotContainsString($secret, json_encode($data));
        $this->assertStringNotContainsString($codes[0], json_encode($data));
        $this->assertFalse($data['sessionManagementSupported']);
    }

    public function test_recovery_regeneration_and_disable_require_password_and_single_use_second_factor(): void
    {
        $user = User::factory()->create();
        [$secret,$codes] = $this->enroll($user);
        $this->postJson('/settings/account/recovery-codes', ['current_password' => 'password'])->assertUnprocessable();
        $this->postJson('/settings/account/recovery-codes', ['current_password' => 'wrong', 'recovery_code' => $codes[0]])->assertUnprocessable();
        $new = $this->postJson('/settings/account/recovery-codes', ['current_password' => 'password', 'recovery_code' => $codes[0]])->assertOk()->json('recoveryCodes');
        $this->assertCount(8, $new);
        $this->assertNotSame($codes, $new);
        $this->deleteJson('/settings/account/totp', ['current_password' => 'password', 'recovery_code' => $codes[1]])->assertUnprocessable();
        $this->deleteJson('/settings/account/totp', ['current_password' => 'password', 'recovery_code' => $new[0]])->assertOk();
        $this->assertNull($user->fresh()->totp_secret);
        $this->assertNull($user->fresh()->totp_enabled_at);
        $this->assertSame([], $user->fresh()->recovery_code_hashes);
    }

    public function test_redirect_recovery_is_encrypted_once_only_and_validation_never_flashes_secrets(): void
    {
        $user = User::factory()->create();
        $setup = $this->actingAs($user)->postJson('/settings/account/totp/setup', ['current_password' => 'password'])->assertOk()->json('setup');
        $this->post('/settings/account/totp/confirm', ['code' => $this->otp($setup['manual_key'])->at(now()->timestamp)])->assertRedirect('/settings/account?tab=security');
        $encrypted = session('security.recovery_once');
        $this->assertIsString($encrypted);
        $codes = $this->getJson('/settings/account')->assertOk()->json('recoveryCodes');
        $this->assertCount(8, $codes);
        $this->assertStringNotContainsString($codes[0], $encrypted);
        $this->assertSame([], $this->getJson('/settings/account')->json('recoveryCodes'));
        $this->from('/settings/account?tab=security')->post('/settings/account/pin', ['current_password' => 'wrong', 'pin' => '012345', 'pin_confirmation' => '012345', 'current_pin' => '888888', 'code' => '999999', 'recovery_code' => 'must-not-flash'])->assertSessionHasErrors('current_password');
        foreach (['current_password', 'pin', 'pin_confirmation', 'current_pin', 'code', 'recovery_code'] as $key) {
            $this->assertArrayNotHasKey($key, session()->getOldInput());
        }
        $this->assertDatabaseHas('security_audit_events', ['event' => 'password.failed', 'user_id' => $user->id]);
        $this->assertStringNotContainsString('must-not-flash', json_encode(DB::table('security_audit_events')->get()));
    }

    public function test_database_sessions_revoke_only_others_and_password_rotation_invalidates_old_sessions(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        config(['session.driver' => 'database']);
        $this->actingAs($user)->getJson('/settings/account')->assertOk();
        $current = session()->getId();
        DB::table('sessions')->insert([
            ['id' => 'other-session', 'user_id' => $user->id, 'ip_address' => '192.0.2.1', 'user_agent' => 'Synthetic test', 'payload' => '', 'last_activity' => now()->timestamp],
            ['id' => 'unrelated-session', 'user_id' => $other->id, 'ip_address' => '192.0.2.2', 'user_agent' => 'Synthetic test', 'payload' => '', 'last_activity' => now()->timestamp],
        ]);
        $data = $this->getJson('/settings/account')->assertOk()->json();
        $this->assertTrue($data['sessionManagementSupported']);
        $this->assertCount(2, $data['activeSessions']);
        $this->assertStringNotContainsString($current, json_encode($data['activeSessions']));
        $this->deleteJson('/settings/account/sessions', ['current_password' => 'wrong'])->assertUnprocessable();
        $this->assertDatabaseHas('sessions', ['id' => 'other-session']);
        $this->deleteJson('/settings/account/sessions', ['current_password' => 'password'])->assertOk();
        $this->assertDatabaseMissing('sessions', ['id' => 'other-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'unrelated-session']);
        $this->getJson('/settings/account')->assertOk();
        $this->withSession(['password_hash_web' => Hash::make('previous-password')])->getJson('/dashboard')->assertUnauthorized();
    }

    public function test_photo_is_validated_reencoded_private_and_only_served_to_its_owner(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user)->postJson('/settings/account/photo', ['photo' => UploadedFile::fake()->image('avatar.png', 50, 50)])->assertOk();
        $path = $user->fresh()->avatar_path;
        $this->assertStringStartsWith('avatars/'.$user->id.'/', $path);
        $this->assertStringEndsWith('.jpg', $path);
        $bytes = Storage::disk('local')->get($path);
        $this->assertSame('image/jpeg', getimagesizefromstring($bytes)['mime']);
        $this->get('/settings/account/avatar')->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->actingAs($other)->get('/settings/account/avatar')->assertNotFound();
        $this->actingAs($user)->postJson('/settings/account/photo', ['photo' => UploadedFile::fake()->createWithContent('avatar.png', '<?php echo "not an image";')])->assertUnprocessable();
        $this->postJson('/settings/account/photo', ['photo' => UploadedFile::fake()->image('avatar.png')->size(2049)])->assertUnprocessable();
        $this->postJson('/settings/account/photo', ['photo' => UploadedFile::fake()->image('avatar.gif')])->assertUnprocessable();
        $this->assertSame($path, $user->fresh()->avatar_path);
        $this->assertDatabaseHas('security_audit_events', ['event' => 'photo.updated', 'user_id' => $user->id]);
    }

    public function test_enrollment_revokes_prior_remember_tokens_and_other_database_sessions(): void
    {
        $user = User::factory()->create(['remember_token' => 'prior-token']);
        config(['session.driver' => 'database']);
        $this->actingAs($user)->getJson('/settings/account')->assertOk();
        DB::table('sessions')->insert(['id' => 'before-enrollment', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp]);
        $this->enroll($user);
        $this->assertNotSame('prior-token', $user->fresh()->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'before-enrollment']);
        $this->getJson('/settings/account')->assertOk();
    }

    public function test_pending_login_expires_cancels_and_rate_limits_without_authentication(): void
    {
        $user = User::factory()->create();
        [$secret,$codes] = $this->enroll($user);
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => true]);
        $pending = session('security.pending_login');
        $this->assertArrayNotHasKey('password', $pending);
        $this->assertStringNotContainsString($secret, json_encode($pending));
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/two-factor-challenge', ['code' => 'invalid'])->assertUnprocessable();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.25'])->postJson('/two-factor-challenge', ['recovery_code' => $codes[0]])->assertStatus(429);
        $this->assertGuest();
        $this->travel(301)->seconds();
        $this->postJson('/two-factor-challenge', ['recovery_code' => $codes[0]])->assertForbidden();
        $this->assertGuest();
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post('/two-factor-challenge/cancel')->assertRedirect('/login');
        $this->postJson('/two-factor-challenge', ['recovery_code' => $codes[0]])->assertForbidden();
        $this->assertGuest();
        $this->assertCount(8, $user->fresh()->recovery_code_hashes);
    }

    public function test_password_and_pin_invalid_input_are_bounded_and_failures_lock_by_account(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/settings/account/pin', ['current_password' => 'password', 'pin' => '12345', 'pin_confirmation' => '12345'])->assertUnprocessable();
        $this->assertNull($user->fresh()->pin_hash);
        for ($i = 0; $i < 5; $i++) {
            $this->putJson('/password', ['current_password' => 'wrong', 'password' => 'Strong-New-Pass123!', 'password_confirmation' => 'Strong-New-Pass123!'])->assertUnprocessable();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.27'])->putJson('/account/password', ['current_password' => 'password', 'password' => 'Strong-New-Pass123!', 'password_confirmation' => 'Strong-New-Pass123!'])->assertStatus(429);
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        $this->travel(301)->seconds();
        $this->putJson('/password', ['current_password' => 'password', 'password' => str_repeat('Long123!', 20), 'password_confirmation' => str_repeat('Long123!', 20)])->assertUnprocessable();
    }

    public function test_expired_setup_cannot_activate_and_password_change_requires_live_second_factor(): void
    {
        $user = User::factory()->create();
        $setup = $this->actingAs($user)->postJson('/settings/account/totp/setup', ['current_password' => 'password'])->json('setup');
        $this->travel(601)->seconds();
        $this->assertNull($this->getJson('/settings/account')->json('securityState.setup'));
        $this->postJson('/settings/account/totp/confirm', ['code' => $this->otp($setup['manual_key'])->at(now()->timestamp)])->assertUnprocessable();
        $this->assertNull($user->fresh()->totp_enabled_at);
        [$secret,$codes] = $this->enroll($user);
        $body = ['current_password' => 'password', 'password' => 'Another-New-Password123!', 'password_confirmation' => 'Another-New-Password123!'];
        $this->putJson('/password', $body)->assertUnprocessable();
        $this->putJson('/account/password', $body + ['recovery_code' => $codes[0]])->assertOk();
        $this->getJson('/settings/account')->assertOk();
        $this->assertTrue(Hash::check('Another-New-Password123!', $user->fresh()->password));
        $this->assertCount(7, $user->fresh()->recovery_code_hashes);
    }

    public function test_migration_up_down_is_additive_to_existing_identity(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'region' => 'A']);
        $password = $user->password;
        $migration = require database_path('migrations/2026_10_30_000001_add_account_security.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'totp_secret'));
        $this->assertSame($password, $user->fresh()->password);
        $migration->up();
        $this->assertTrue(Schema::hasColumn('users', 'totp_secret'));
        $this->assertNull($user->fresh()->totp_enabled_at);
        $this->assertNull($user->fresh()->pin_hash);
        $this->assertSame('A', $user->fresh()->region);
        $this->assertSame('admin', $user->fresh()->role);
    }

    public function test_real_current_login_survives_password_rotation_across_fresh_guard(): void
    {
        $user = User::factory()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->putJson('/account/password', ['current_password' => 'password', 'password' => 'New-Secure-Password123!', 'password_confirmation' => 'New-Secure-Password123!'])->assertOk();
        Auth::forgetGuards();
        $this->getJson('/settings/account')->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_photo_processing_strips_original_payload_and_rejects_decode_failure(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $image = UploadedFile::fake()->image('clean.png', 16, 16);
        $raw = file_get_contents($image->getRealPath());
        $this->actingAs($user)->postJson('/settings/account/photo', ['photo' => UploadedFile::fake()->createWithContent('photo.png', $raw.'<?php dangerous-payload ?>')])->assertOk();
        $bytes = Storage::disk('local')->get($user->fresh()->avatar_path);
        $this->assertStringNotContainsString('dangerous-payload', $bytes);
        $this->assertSame('private', Storage::disk('local')->getVisibility($user->fresh()->avatar_path));
        $this->postJson('/settings/account/photo', ['photo' => UploadedFile::fake()->createWithContent('photo.php', $raw)])->assertUnprocessable();
        $this->postJson('/settings/account/photo', ['photo' => UploadedFile::fake()->createWithContent('broken.png', substr($raw, 0, 33))])->assertUnprocessable();
    }

    public function test_pin_rejects_trailing_newline_and_password_rejects_array_without_server_error(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/settings/account/pin', ['current_password' => 'password', 'pin' => "123456\n", 'pin_confirmation' => "123456\n"])->assertUnprocessable();
        $this->assertNull($user->fresh()->pin_hash);
        $this->putJson('/account/password', ['current_password' => 'password', 'password' => ['invalid'], 'password_confirmation' => ['invalid']])->assertUnprocessable();
    }

    public function test_password_aliases_share_current_password_policy_audit_and_rotation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->putJson('/account/password', ['current_password' => 'wrong', 'password' => 'New-Secure-Password123!', 'password_confirmation' => 'New-Secure-Password123!'])->assertUnprocessable();
        $this->putJson('/password', ['current_password' => 'password', 'password' => 'short', 'password_confirmation' => 'short'])->assertUnprocessable();
        $this->putJson('/account/password', ['current_password' => 'password', 'password' => 'New-Secure-Password123!', 'password_confirmation' => 'New-Secure-Password123!'])->assertOk();
        $this->assertTrue(Hash::check('New-Secure-Password123!', $user->fresh()->password));
        $this->assertNotNull($user->fresh()->remember_token);
        $this->assertDatabaseHas('security_audit_events', ['user_id' => $user->id, 'event' => 'password.changed']);
        $this->assertStringNotContainsString('New-Secure', json_encode(DB::table('security_audit_events')->get()));
        $this->putJson('/password', ['current_password' => 'New-Secure-Password123!', 'password' => 'Another-Secure123!', 'password_confirmation' => 'Another-Secure123!'])->assertOk();
    }
}
