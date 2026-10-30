<?php

use App\Http\Middleware\AccountSecurityResponse;
use App\Http\Middleware\CheckRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trimStrings(except: ['pin', 'pin_confirmation', 'current_pin', 'code', 'recovery_code']);
        $middleware->web(append: [AccountSecurityResponse::class]);
        $middleware->alias([
            'role' => CheckRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['current_password', 'password', 'password_confirmation', 'pin', 'pin_confirmation', 'current_pin', 'code', 'recovery_code', 'totp_secret', 'totp_pending_secret', 'manual_key', 'recoveryCodes']);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
