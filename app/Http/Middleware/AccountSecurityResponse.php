<?php

namespace App\Http\Middleware;

use App\Services\AccountSecurity;
use Closure;
use Illuminate\Http\Request;

class AccountSecurityResponse
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $response = $next($request);
        } finally {
            // Controller transactions have rolled back before recording a failed check.
            if ($failure = $request->attributes->get('security.failure')) {
                app(AccountSecurity::class)->audit($request, $failure[0], $failure[1]);
                $request->attributes->remove('security.failure');
            }
        }
        if ($request->is('settings/account*', 'two-factor-challenge*', 'account/password', 'password', 'login')) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Referrer-Policy', 'same-origin');
            $response->headers->set('X-Content-Type-Options', 'nosniff');
        }

        return $response;
    }
}
