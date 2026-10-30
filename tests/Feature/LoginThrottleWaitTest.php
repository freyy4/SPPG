<?php

namespace Tests\Feature;

use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LoginThrottleWaitTest extends TestCase
{
    public function createApplication()
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->loadEnvironmentFrom('.env.login-throttle-test-not-present');
        $this->traitsUsedByTest = class_uses_recursive(static::class);
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfSecond());
        Event::fake([Lockout::class]);
    }

    public function test_account_only_lockout_reports_remaining_account_wait(): void
    {
        $request = $this->loginRequest();
        $key = $this->accountKey();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            RateLimiter::hit($key, 300);
        }
        $this->travel(17)->seconds();

        $this->assertWait($request, 283);
        $this->assertSame(5, RateLimiter::attempts($key));
        $this->assertSame(0, RateLimiter::attempts($request->throttleKey()));
    }

    public function test_ip_only_lockout_ignores_longer_unblocked_account_timer(): void
    {
        $request = $this->loginRequest();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            RateLimiter::hit($request->throttleKey());
        }
        RateLimiter::hit($this->accountKey(), 300);
        $this->travel(17)->seconds();

        $this->assertWait($request, 43);
        $this->assertSame(5, RateLimiter::attempts($request->throttleKey()));
        $this->assertSame(1, RateLimiter::attempts($this->accountKey()));
    }

    public function test_both_lockouts_report_longest_remaining_wait(): void
    {
        $request = $this->loginRequest();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            RateLimiter::hit($request->throttleKey());
            RateLimiter::hit($this->accountKey(), 300);
        }
        $this->travel(17)->seconds();

        $this->assertWait($request, 283);
        $this->assertSame(5, RateLimiter::attempts($request->throttleKey()));
        $this->assertSame(5, RateLimiter::attempts($this->accountKey()));
    }

    public function test_account_wait_remains_after_local_lockout_expires(): void
    {
        $request = $this->loginRequest();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            RateLimiter::hit($request->throttleKey());
            RateLimiter::hit($this->accountKey(), 300);
        }
        $this->travel(61)->seconds();

        $this->assertWait($request, 239);
        $this->assertSame(5, RateLimiter::attempts($this->accountKey()));
    }

    public function test_neither_blocked_returns_without_lockout_or_counter_changes(): void
    {
        $request = $this->loginRequest();
        $request->ensureIsNotRateLimited();
        for ($attempt = 0; $attempt < 4; $attempt++) {
            RateLimiter::hit($request->throttleKey());
            RateLimiter::hit($this->accountKey(), 300);
        }

        $request->ensureIsNotRateLimited();

        Event::assertNotDispatched(Lockout::class);
        $this->assertSame(4, RateLimiter::attempts($request->throttleKey()));
        $this->assertSame(4, RateLimiter::attempts($this->accountKey()));
    }

    private function loginRequest(): LoginRequest
    {
        return LoginRequest::create('/login', 'POST', ['email' => 'Wait.Example@example.test'], [], [], ['REMOTE_ADDR' => '192.0.2.10']);
    }

    private function accountKey(): string
    {
        return 'login:account:'.hash('sha256', 'wait.example@example.test');
    }

    private function assertWait(LoginRequest $request, int $seconds): void
    {
        try {
            $request->ensureIsNotRateLimited();
            $this->fail('A blocked login must report its retry interval.');
        } catch (ValidationException $exception) {
            $this->assertSame([
                'email' => [trans('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)])],
            ], $exception->errors());
        }
        Event::assertDispatched(Lockout::class, 1);
    }
}
