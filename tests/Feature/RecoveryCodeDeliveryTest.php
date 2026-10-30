<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use OTPHP\TOTP;
use Tests\TestCase;

class RecoveryCodeDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_security_html_preserves_encrypted_codes_until_security_displays_once(): void
    {
        $user = User::factory()->create();
        $setup = $this->actingAs($user)->postJson('/settings/account/totp/setup', ['current_password' => 'password'])->assertOk()->json('setup');
        $this->post('/settings/account/totp/confirm', ['code' => TOTP::createFromSecret($setup['manual_key'])->at(now()->timestamp)])
            ->assertRedirect('/settings/account?tab=security');
        $encrypted = session('security.recovery_once');
        $this->assertIsString($encrypted);
        $payload = Crypt::decrypt($encrypted);
        $codes = $payload['codes'];
        $this->assertCount(8, $codes);
        $this->assertSame($user->id, $payload['user_id']);
        $this->assertGreaterThan(now()->timestamp, $payload['expires']);
        foreach ($codes as $code) {
            $this->assertStringNotContainsString($code, $encrypted);
        }

        foreach (['?tab=profile', '?tab=account', '', '?tab=invalid'] as $suffix) {
            $response = $this->get('/settings/account'.$suffix)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
            foreach ($codes as $code) {
                $response->assertDontSee($code);
            }
            // Check retention without logging any real recovery codes on failure.
            $this->assertTrue(session('security.recovery_once') === $encrypted, 'Non-security HTML consumed pending encrypted delivery');
            $response->assertViewHas('recoveryCodes', []);
        }

        $response = $this->get('/settings/account?tab=security')->assertOk();
        foreach ($codes as $code) {
            $response->assertSee($code);
        }
        $this->assertFalse(session()->has('security.recovery_once'));
        $refresh = $this->get('/settings/account?tab=security')->assertOk()->assertViewHas('recoveryCodes', []);
        foreach ($codes as $code) {
            $refresh->assertDontSee($code);
        }
    }

    public function test_json_delivers_pending_codes_once_regardless_of_tab(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach (['', '?tab=profile', '?tab=account', '?tab=security'] as $suffix) {
            $codes = ['synthetic-delivery-fixture'];
            $encrypted = Crypt::encrypt(['user_id' => $user->id, 'expires' => now()->addMinutes(5)->timestamp, 'codes' => $codes]);
            $this->withSession(['security.recovery_once' => $encrypted]);
            $this->getJson('/settings/account'.$suffix)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('recoveryCodes', $codes);
            $this->assertFalse(session()->has('security.recovery_once'));
            $this->getJson('/settings/account'.$suffix)->assertOk()->assertJsonPath('recoveryCodes', []);
        }
    }

    public function test_expired_or_other_user_payload_is_never_delivered_by_html_or_json(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user);
        foreach (['expired', 'wrong-user'] as $reason) {
            foreach (['html', 'json'] as $format) {
                $codes = ['synthetic-undeliverable-fixture'];
                $encrypted = Crypt::encrypt([
                    'user_id' => $reason === 'wrong-user' ? $other->id : $user->id,
                    'expires' => $reason === 'expired' ? now()->timestamp : now()->addMinutes(5)->timestamp,
                    'codes' => $codes,
                ]);
                $this->withSession(['security.recovery_once' => $encrypted]);
                $this->get('/settings/account?tab=profile')->assertOk()->assertDontSee($codes[0]);
                $this->assertTrue(session('security.recovery_once') === $encrypted);
                if ($format === 'json') {
                    $this->getJson('/settings/account')->assertOk()->assertJsonPath('recoveryCodes', []);
                } else {
                    $this->get('/settings/account?tab=security')->assertOk()->assertViewHas('recoveryCodes', [])->assertDontSee($codes[0]);
                }
                $this->assertFalse(session()->has('security.recovery_once'));
            }
        }
    }
}
