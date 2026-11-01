<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives an app sign-in its own web session, so the web's controllers work unchanged for the app:
 * what the buyer ticked for checkout, a "Buy now" item, the vouchers chosen, the email-change code, …
 * The session belongs to the login token (a new login starts a new one).
 */
class ApiWebSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user  = $request->user();
        $token = $user?->currentAccessToken();
        $key   = $token && isset($token->id) ? 'token:' . $token->id : ($user ? 'user:' . $user->id : null);

        $session = app('session')->driver();
        if ($key) {
            $session->setId(substr(hash('sha256', 'api-session|' . $key . '|' . config('app.key')), 0, 40));
        }
        $session->start();
        $request->setLaravelSession($session);

        $response = $next($request);

        $session->save();

        return $response;
    }
}
