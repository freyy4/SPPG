<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccountSecurity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TwoFactorChallengeController extends Controller
{
    public function __construct(private AccountSecurity $security) {}

    private function pending(Request $request): array
    {
        $pending = $request->session()->get('security.pending_login');
        if (! is_array($pending) || ($pending['expires'] ?? 0) <= now()->timestamp) {
            $request->session()->forget('security.pending_login');
            abort(403, 'Sesi login kedaluwarsa. Silakan masuk kembali.');
        }

        return $pending;
    }

    public function show(Request $request)
    {
        $this->pending($request);

        return $request->expectsJson() ? response()->json(['challenge' => true]) : response()->view('auth.two-factor-challenge');
    }

    public function verify(Request $request)
    {
        $pending = $this->pending($request);
        $user = DB::transaction(function () use ($request, $pending) {
            $user = User::whereKey($pending['id'])->lockForUpdate()->first();
            if (! $user || ! $user->totp_enabled_at || ! hash_equals($pending['password_fingerprint'], hash('sha256', $user->password))) {
                $request->session()->forget('security.pending_login');
                abort(403, 'Silakan masuk kembali.');
            }
            $this->security->secondFactor($request, $user);
            $this->security->audit($request, 'totp.verified', $user);
            $this->security->audit($request, 'login.succeeded', $user);

            return $user;
        });
        $request->session()->forget('security.pending_login');
        $request->session()->regenerate(true);
        Auth::login($user, (bool) $pending['remember']);
        $request->session()->put('show_login_popup', true);

        return $request->expectsJson() ? response()->json(['status' => 'authenticated']) : redirect()->intended(route('dashboard', absolute: false));
    }

    public function cancel(Request $request)
    {
        $this->security->audit($request, 'login.cancelled');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
