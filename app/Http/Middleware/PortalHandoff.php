<?php

namespace App\Http\Middleware;

use App\Support\Portal;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Moves a login to the portal it belongs to. When a page in one portal signs an account in (or
 * holds a sign-up code) and then redirects into another portal — e.g. a seller logging in on the
 * main login page, sent to the Seller Centre — the redirect goes through a one-time hand-off:
 * the account is signed in to the target portal (with its messages and "remember me"), and the
 * current portal is left as it was if the account doesn't belong there.
 *
 * The token is random, single-use, expires in 2 minutes, and only works in the same browser
 * (it is bound to that browser's session cookie of the portal that created it).
 */
class PortalHandoff
{
    public const TTL_SECONDS = 120;

    /** Session values that move with a sign-up still waiting for its email code. */
    private const CARRY = ['otp.user_id', 'otp.remember'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! Portal::enabled() || ! $response instanceof RedirectResponse || ! $request->hasSession()) {
            return $response;
        }
        $url = $response->getTargetUrl();
        if (parse_url($url, PHP_URL_HOST) && parse_url($url, PHP_URL_HOST) !== $request->getHost()) {
            return $response;   // leaving the site
        }
        $from = Portal::current();
        $to = Portal::fromPath((string) parse_url($url, PHP_URL_PATH));
        if ($to === $from) {
            return $response;
        }

        $session = $request->session();
        $user = Auth::user();
        $carry = array_filter(array_combine(self::CARRY, array_map(fn ($k) => $session->get($k), self::CARRY)), fn ($v) => $v !== null);
        if ($user && ! Portal::allows($user, $to)) {
            return $response;   // e.g. a buyer following a link to the Seller Centre: nothing to move
        }
        if (! $user && ! $carry) {
            return $response;
        }

        $guard = Auth::guard('web');
        $remember = $user && Cookie::hasQueued($guard->getRecallerName());

        // Messages flashed for the next page go with the account.
        $flash = [];
        foreach ((array) $session->get('_flash.new', []) as $key) {
            $flash[$key] = $session->get($key);
        }

        $token = Str::random(48);
        Cache::put('portal-handoff:' . $token, [
            'from' => $from, 'to' => $to, 'from_session' => $session->getId(),
            'user_id' => $user?->id, 'remember' => $remember, 'url' => $url, 'flash' => $flash, 'carry' => $carry,
        ], self::TTL_SECONDS);

        // Leave this portal as it was: the account isn't kept signed in where it doesn't belong.
        if ($user && ! Portal::allows($user, $from)) {
            $session->forget($guard->getName());
            $guard->forgetUser();
            Cookie::unqueue($guard->getRecallerName());
        }
        foreach (array_keys($carry) as $key) {
            $session->forget($key);
        }
        foreach (array_keys($flash) as $key) {
            $session->forget($key);
        }
        $session->put('_flash.new', []);

        return redirect()->to(Portal::handoffUrl($to, $token));
    }
}
