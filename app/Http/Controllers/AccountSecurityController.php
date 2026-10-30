<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccountSecurity;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AccountSecurityController extends Controller
{
    public function __construct(protected AccountSecurity $security) {}

    protected function result(Request $request, string $status, array $data = [], string $tab = 'security')
    {
        if (isset($data['recoveryCodes'])) {
            $request->session()->forget('security.recovery_once');
        }
        if ($request->expectsJson()) {
            return response()->json(['status' => $status] + $data)->header('Cache-Control', 'no-store, private');
        }
        if (isset($data['recoveryCodes'])) {
            $request->session()->put('security.recovery_once', Crypt::encrypt(['user_id' => $request->user()->id, 'expires' => now()->addMinutes(5)->timestamp, 'codes' => $data['recoveryCodes']]));
        }

        return redirect()->route('account.settings', ['tab' => $tab])->with('status', $status)->header('Cache-Control', 'no-store, private');
    }

    public function settings(Request $request)
    {
        $user = $request->user()->fresh();
        $recoveryCodes = [];
        // HTML only displays recovery codes on the security tab; JSON explicitly
        // delivers them regardless of tab under the existing once-only contract.
        if (($request->expectsJson() || $request->input('tab') === 'security') && ($encrypted = $request->session()->pull('security.recovery_once'))) {
            $payload = Crypt::decrypt($encrypted);
            if (($payload['user_id'] ?? null) === $user->id && ($payload['expires'] ?? 0) > now()->timestamp) {
                $recoveryCodes = $payload['codes'];
            }
        }
        $activeSessions = [];
        if ($this->security->sessionsSupported()) {
            $activeSessions = DB::connection(config('session.connection'))->table(config('session.table'))->where('user_id', $user->id)->orderByDesc('last_activity')->get()->map(fn ($s) => [
                'id' => hash('sha256', $s->id), 'ip_address' => $s->ip_address, 'user_agent' => $s->user_agent,
                'last_active_at' => Carbon::createFromTimestamp($s->last_activity)->toIso8601String(), 'is_current' => hash_equals($s->id, $request->session()->getId()),
            ])->all();
        }
        $data = ['user' => $user, 'securityState' => ['pin_enabled' => (bool) $user->pin_hash, 'totp_enabled' => (bool) $user->totp_enabled_at, 'recovery_codes_remaining' => count($user->recovery_code_hashes ?? []), 'setup' => $this->security->setupPayload($user)], 'activeSessions' => $activeSessions, 'sessionManagementSupported' => $this->security->sessionsSupported(), 'recoveryCodes' => $recoveryCodes];

        return ($request->expectsJson() ? response()->json($data) : response()->view('settings.account', $data))->header('Cache-Control', 'no-store, private');
    }

    public function profile(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:32', 'regex:/^[+0-9 ()-]+$/'], 'email' => ['prohibited'], 'role' => ['prohibited'], 'region' => ['prohibited'], 'avatar_path' => ['prohibited'], 'user_id' => ['prohibited']]);
        $request->user()->forceFill(collect($data)->only(['name', 'phone'])->all())->save();
        $this->security->audit($request, 'profile.updated');

        return $this->result($request, 'profile-updated', [], 'profile');
    }

    public function pin(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $verifyOnly = $request->routeIs('account.pin.verify');
            if (! $verifyOnly) {
                $this->security->reauthenticate($request, $user);
            }
            if ($verifyOnly || ! $request->isMethod('post')) {
                $field = $verifyOnly ? 'pin' : 'current_pin';
                $this->security->limited($request, 'pin', $user, function () use ($request, $user, $field) {
                    if (! $user->pin_hash || ! is_string($request->input($field)) || ! preg_match('/^[0-9]{6}$/D', $request->input($field)) || ! Hash::check($request->input($field), $user->pin_hash)) {
                        throw ValidationException::withMessages([$field => 'PIN tidak sesuai.']);
                    }
                });
            }
            if ($verifyOnly) {
                $request->session()->put('security.pin_verified_at', now()->timestamp);
                $this->security->audit($request, 'pin.verified', $user);

                return $this->result($request, 'pin-verified');
            }
            if ($request->isMethod('delete')) {
                $hash = null;
                $event = 'pin.disabled';
            } else {
                if ($request->isMethod('post') && $user->pin_hash) {
                    abort(409, 'PIN sudah aktif.');
                }
                $data = $request->validate(['pin' => ['required', 'string', 'regex:/^[0-9]{6}$/D', 'confirmed']]);
                $hash = Hash::make($data['pin']);
                $event = $request->isMethod('post') ? 'pin.enabled' : 'pin.changed';
            }
            $user->forceFill(['pin_hash' => $hash])->save();
            $request->session()->forget('security.pin_verified_at');
            $this->security->audit($request, $event, $user);

            return $this->result($request, str_replace('.', '-', $event));
        });
    }

    public function totpSetup(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $this->security->reauthenticate($request, $user);
            abort_if((bool) $user->totp_enabled_at, 409, '2FA sudah aktif.');
            $user->forceFill(['totp_pending_secret' => $this->security->otp()->getSecret(), 'totp_pending_expires_at' => now()->addMinutes(10)])->save();
            $this->security->audit($request, 'totp.setup', $user);

            return $this->result($request, 'totp-setup', ['setup' => $this->security->setupPayload($user)]);
        });
    }

    public function totpConfirm(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $this->security->limited($request, 'totp', $user, function () use ($request, $user) {
                if ($user->totp_enabled_at || ! $user->totp_pending_secret || ! $user->totp_pending_expires_at?->isFuture() || ! $this->security->consumeTotp($user, $request->input('code'), $user->totp_pending_secret)) {
                    throw ValidationException::withMessages(['code' => 'Kode tidak sesuai atau setup kedaluwarsa.']);
                }
            });
            $user->forceFill(['totp_secret' => $user->totp_pending_secret, 'totp_enabled_at' => now(), 'totp_pending_secret' => null, 'totp_pending_expires_at' => null])->save();
            $codes = $this->security->generateRecovery($user);
            $this->security->revokeOthers($request, $user);
            $this->security->audit($request, 'totp.enabled', $user);

            return $this->result($request, 'totp-enabled', ['recoveryCodes' => $codes]);
        });
    }

    public function totpDisable(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $this->security->reauthenticate($request, $user);
            $this->security->secondFactor($request, $user);
            $user->forceFill(['totp_secret' => null, 'totp_enabled_at' => null, 'totp_last_step' => null, 'totp_pending_secret' => null, 'totp_pending_expires_at' => null, 'recovery_code_hashes' => []])->save();
            $this->security->revokeOthers($request, $user);
            $request->session()->forget('security.recovery_once');
            $this->security->audit($request, 'totp.disabled', $user);

            return $this->result($request, 'totp-disabled');
        });
    }

    public function recoveryRegenerate(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $this->security->reauthenticate($request, $user);
            $this->security->secondFactor($request, $user);
            $codes = $this->security->generateRecovery($user);
            $this->security->audit($request, 'recovery.regenerated', $user);

            return $this->result($request, 'recovery-regenerated', ['recoveryCodes' => $codes]);
        });
    }

    public function sessionsRevoke(Request $request)
    {
        abort_unless($this->security->sessionsSupported(), 409, 'Driver sesi tidak mendukung pencabutan sesi lain.');

        return DB::transaction(function () use ($request) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $this->security->reauthenticate($request, $user);
            $this->security->revokeOthers($request, $user);
            $this->security->audit($request, 'sessions.revoked', $user);

            return $this->result($request, 'sessions-revoked');
        });
    }

    public function photo(Request $request)
    {
        $data = $request->validate(['photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'extensions:jpg,jpeg,png', 'max:2048']]);
        $file = $data['photo'];
        $info = @getimagesize($file->getRealPath());
        if (! $info || ! in_array($info['mime'], ['image/jpeg', 'image/png'], true) || $info[0] > 4096 || $info[1] > 4096 || $info[0] * $info[1] > 16000000) {
            throw ValidationException::withMessages(['photo' => 'Foto tidak dapat diproses atau dimensi terlalu besar.']);
        }
        $image = @imagecreatefromstring(file_get_contents($file->getRealPath()));
        if (! $image) {
            throw ValidationException::withMessages(['photo' => 'Foto tidak dapat diproses.']);
        }
        $width = min(512, $info[0]);
        $height = max(1, (int) round($info[1] * $width / $info[0]));
        $processed = imagecreatetruecolor($width, $height);
        imagefill($processed, 0, 0, imagecolorallocate($processed, 255, 255, 255));
        imagecopyresampled($processed, $image, 0, 0, 0, 0, $width, $height, $info[0], $info[1]);
        ob_start();
        imagejpeg($processed, null, 85);
        $bytes = ob_get_clean();
        imagedestroy($image);
        imagedestroy($processed);
        $disk = Storage::disk('local');
        $path = 'avatars/'.$request->user()->id.'/'.bin2hex(random_bytes(16)).'.jpg';
        abort_unless($disk->put($path, $bytes, ['visibility' => 'private']), 500, 'Foto gagal disimpan.');
        try {
            $old = DB::transaction(function () use ($request, $path) {
                $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $old = $user->avatar_path;
                $user->forceFill(['avatar_path' => $path])->save();
                $this->security->audit($request, 'photo.updated', $user);

                return $old;
            });
        } catch (\Throwable $e) {
            $disk->delete($path);
            throw $e;
        }
        if ($old && preg_match('#^avatars/'.(int) $request->user()->id.'/[a-f0-9]{32}\\.jpg$#D', $old)) {
            $disk->delete($old);
        }

        return $this->result($request, 'photo-updated', ['avatar_url' => route('account.avatar')], 'profile');
    }

    public function avatar(Request $request)
    {
        $path = $request->user()->fresh()->avatar_path;
        abort_unless($path && preg_match('#^avatars/'.(int) $request->user()->id.'/[a-f0-9]{32}\\.jpg$#D', $path), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        return response($disk->get($path))->header('Content-Type', 'image/jpeg')->header('Cache-Control', 'no-store, private')->header('X-Content-Type-Options', 'nosniff');
    }

    public function password(Request $request)
    {
        $user = $request->user();

        return DB::transaction(function () use ($request, $user) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->security->reauthenticate($request, $user);
            if ($user->totp_enabled_at) {
                $this->security->secondFactor($request, $user);
            }
            $data = $request->validate(['password' => ['bail', 'required', 'string', 'max:72', function ($attribute, $value, $fail) {
                if (strlen($value) > 72) {
                    $fail('Password maksimal 72 byte.');
                }
            }, Password::min(12)->mixedCase()->numbers()->symbols(), 'confirmed']]);
            $user->forceFill(['password' => Hash::make($data['password'])])->save();
            $this->security->revokeOthers($request, $user);
            $request->session()->regenerate();
            $this->security->audit($request, 'password.changed', $user);

            return $this->result($request, 'password-updated');
        });
    }
}
