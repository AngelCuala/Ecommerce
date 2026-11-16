<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Login time limits, checked on every request of a signed-in account:
 *  - idle:     signed out after config('session.lifetime') minutes without any request (a week);
 *  - absolute: signed out config('session.max_login_minutes') after signing in, however active
 *              (a week). "Remember me" can't stretch it: its cookie lasts no longer.
 * The account is properly logged out (session cleared, remember token rotated) and sent to its
 * login page with the reason.
 */
class EnforceLoginTimeLimit
{
    public const STARTED = 'auth.started_at';
    public const SEEN    = 'auth.last_seen_at';

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check() || ! $request->hasSession()) {
            return $next($request);
        }

        $session = $request->session();
        $now = now()->getTimestamp();
        $started = (int) $session->get(self::STARTED, 0);
        $seen = (int) $session->get(self::SEEN, 0);

        if (! $started) {
            $session->put(self::STARTED, $started = $now);   // e.g. signed in before this rule existed
        }

        $max = (int) config('session.max_login_minutes', 480);
        $idle = (int) config('session.lifetime', 30);
        if ($max > 0 && $now - $started >= $max * 60) {
            return $this->signOut($request, 'For your security you were signed out after ' . self::duration($max) . '. Please log in again.');
        }
        if ($idle > 0 && $seen && $now - $seen >= $idle * 60) {
            return $this->signOut($request, 'You were signed out after ' . self::duration($idle) . ' of inactivity. Please log in again.');
        }

        $session->put(self::SEEN, $now);

        return $next($request);
    }

    private function signOut(Request $request, string $message): Response
    {
        $user = Auth::user();
        Auth::guard('web')->logout();   // also rotates the remember-me token, so that cookie stops working
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 401);
        }

        $login = match (true) {
            $user?->role === 'sorting_center'          => 'sc.login',
            default                                    => 'login',
        };

        return redirect()->route($login)->with('error', $message)->withErrors(['email' => $message]);
    }

    public static function duration(int $minutes): string
    {
        if ($minutes % 1440 === 0 && $minutes >= 1440) {
            return $minutes === 10080 ? 'a week' : ($minutes / 1440) . ' days';
        }
        return $minutes % 60 === 0 && $minutes >= 60
            ? ($minutes / 60) . ' ' . ($minutes === 60 ? 'hour' : 'hours')
            : $minutes . ' minutes';
    }
}
