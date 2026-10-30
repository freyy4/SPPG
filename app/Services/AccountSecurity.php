<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OTPHP\TOTP;
use Psr\Clock\ClockInterface;

class AccountSecurity
{
    private const EVENTS = ['password.changed', 'password.failed', 'pin.enabled', 'pin.changed', 'pin.disabled', 'pin.verified', 'pin.failed', 'totp.setup', 'totp.enabled', 'totp.disabled', 'totp.failed', 'totp.verified', 'recovery.regenerated', 'sessions.revoked', 'profile.updated', 'photo.updated', 'login.failed', 'login.succeeded', 'login.cancelled'];

    public function audit(Request $request, string $event, ?User $user = null): void
    {
        if (! in_array($event, self::EVENTS, true)) {
            throw new \LogicException('Unknown security event');
        }
        DB::table('security_audit_events')->insert(['user_id' => $user?->id ?? $request->user()?->id, 'event' => $event, 'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 512), 'created_at' => now()]);
    }

    public function limited(Request $request, string $purpose, User $user, callable $check): mixed
    {
        $keys = ['security:'.$purpose.':'.$user->id, 'security:'.$purpose.':'.$user->id.':'.hash('sha256', (string) $request->ip())];
        foreach ($keys as $key) {
            if (RateLimiter::tooManyAttempts($key, 5)) {
                $request->attributes->set('security.failure', [$purpose === 'pin' ? 'pin.failed' : ($purpose === 'password' ? 'password.failed' : 'totp.failed'), $user]);
                abort(429, 'Terlalu banyak percobaan. Coba lagi nanti.');
            }
        }
        foreach ($keys as $key) {
            RateLimiter::hit($key, 300);
        }
        try {
            $result = $check();
        } catch (ValidationException $e) {
            $request->attributes->set('security.failure', [$purpose === 'pin' ? 'pin.failed' : ($purpose === 'password' ? 'password.failed' : 'totp.failed'), $user]);
            throw $e;
        }
        foreach ($keys as $key) {
            RateLimiter::clear($key);
        }

        return $result;
    }

    public function reauthenticate(Request $request, User $user): void
    {
        $this->limited($request, 'password', $user, function () use ($request, $user) {
            if (! is_string($request->input('current_password')) || ! Hash::check($request->input('current_password'), $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'Password saat ini tidak sesuai.']);
            }
        });
    }

    public function otp(?string $secret = null): TOTP
    {
        $clock = new class implements ClockInterface
        {
            public function now(): \DateTimeImmutable
            {
                return now()->toDateTimeImmutable();
            }
        };

        return $secret === null ? TOTP::generate($clock) : TOTP::createFromSecret($secret, $clock);
    }

    public function setupPayload(User $user): ?array
    {
        if (! $user->totp_pending_secret || ! $user->totp_pending_expires_at?->isFuture() || $user->totp_enabled_at) {
            return null;
        }
        $otp = $this->otp($user->totp_pending_secret);
        $otp->setLabel($user->email);
        $otp->setIssuer('Sentra Buah Garut');
        $uri = $otp->getProvisioningUri();
        $writer = new Writer(new GDLibRenderer(320));

        return ['manual_key' => $otp->getSecret(), 'provisioning_uri' => $uri, 'qr_data_uri' => 'data:image/png;base64,'.base64_encode($writer->writeString($uri))];
    }

    /** Caller must hold the users row lock within a transaction. */
    public function consumeTotp(User $user, mixed $code, string $secret): bool
    {
        if (! is_string($code) || ! preg_match('/^[0-9]{6}$/D', $code)) {
            return false;
        }
        $otp = $this->otp($secret);
        $current = intdiv(now()->timestamp, 30);
        foreach ([$current, $current - 1, $current + 1] as $step) {
            if ($step > ($user->totp_last_step ?? -1) && $otp->verify($code, $step * 30)) {
                $user->forceFill(['totp_last_step' => $step])->save();

                return true;
            }
        }

        return false;
    }

    /** Caller must hold the users row lock within a transaction. */
    public function secondFactor(Request $request, User $user): void
    {
        $this->limited($request, 'totp', $user, function () use ($request, $user) {
            if (! $user->totp_enabled_at) {
                throw ValidationException::withMessages(['code' => '2FA tidak aktif.']);
            }
            $recovery = $request->input('recovery_code');
            if (is_string($recovery) && $recovery !== '') {
                if (! preg_match('/^[a-f0-9]{20}$/D', $recovery)) {
                    throw ValidationException::withMessages(['code' => 'Kode tidak sesuai atau sudah digunakan.']);
                }
                $hashes = $user->recovery_code_hashes ?? [];
                foreach ($hashes as $index => $hash) {
                    if (Hash::check($recovery, $hash)) {
                        unset($hashes[$index]);
                        $user->forceFill(['recovery_code_hashes' => array_values($hashes)])->save();

                        return;
                    }
                }
            } elseif ($this->consumeTotp($user, $request->input('code'), $user->totp_secret)) {
                return;
            }
            throw ValidationException::withMessages(['code' => 'Kode tidak sesuai atau sudah digunakan.']);
        });
    }

    public function generateRecovery(User $user): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = bin2hex(random_bytes(10));
        }
        $user->forceFill(['recovery_code_hashes' => array_map(fn ($code) => Hash::make($code), $codes)])->save();

        return $codes;
    }

    public function sessionsSupported(): bool
    {
        return config('session.driver') === 'database';
    }

    public function revokeOthers(Request $request, User $user): void
    {
        if ($this->sessionsSupported()) {
            DB::connection(config('session.connection'))->table(config('session.table'))->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();
        }
        $user->forceFill(['remember_token' => Str::random(60)])->save();
        // AuthenticateSession writes after the response; refresh its guard identity too.
        Auth::guard('web')->setUser($user);
        $request->session()->put('password_hash_web', Auth::guard('web')->hashPasswordForCookie($user->password));
    }
}
