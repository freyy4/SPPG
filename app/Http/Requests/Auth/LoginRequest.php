<?php

namespace App\Http\Requests\Auth;

use App\Services\AccountSecurity;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $security = app(AccountSecurity::class);
        // SessionGuard validates inside its timing-safe envelope without logging in.
        $valid = Auth::validate($this->only('email', 'password'));
        $user = Auth::getLastAttempted();
        if (! $valid) {
            RateLimiter::hit($this->throttleKey());
            RateLimiter::hit('login:account:'.hash('sha256', Str::lower((string) $this->input('email'))), 300);
            $security->audit($this, 'login.failed', $user);
            throw ValidationException::withMessages(['email' => 'E-mail atau password salah, silahkan hubungi SuperAdmin']);
        }
        RateLimiter::clear($this->throttleKey());
        RateLimiter::clear('login:account:'.hash('sha256', Str::lower((string) $this->input('email'))));
        $this->session()->forget('security.pending_login');
        $this->session()->regenerate(true);
        if ($user->totp_enabled_at) {
            $this->session()->put('security.pending_login', ['id' => $user->id, 'remember' => $this->boolean('remember'), 'expires' => now()->addMinutes(5)->timestamp, 'password_fingerprint' => hash('sha256', $user->password)]);

            return;
        }
        Auth::login($user, $this->boolean('remember'));
        $security->audit($this, 'login.succeeded', $user);
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        $accountKey = 'login:account:'.hash('sha256', Str::lower((string) $this->input('email')));
        $ipBlocked = RateLimiter::tooManyAttempts($this->throttleKey(), 5);
        $accountBlocked = RateLimiter::tooManyAttempts($accountKey, 5);

        if (! $ipBlocked && ! $accountBlocked) {
            return;
        }

        event(new Lockout($this));

        $seconds = max(
            $ipBlocked ? RateLimiter::availableIn($this->throttleKey()) : 0,
            $accountBlocked ? RateLimiter::availableIn($accountKey) : 0,
        );

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
