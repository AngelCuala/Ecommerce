<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // alvy.trade is served through Cloudflare, which talks to this app over plain HTTP.
        // Trust its X-Forwarded-* headers so URLs (e.g. the Google sign-in callback) are https://.
        $middleware->trustProxies(at: '*');

        // Every action that changes something (by any account) goes to the activity log, with IP.
        $middleware->web(append: [\App\Http\Middleware\LogUserActivity::class]);
        $middleware->api(append: [\App\Http\Middleware\LogUserActivity::class]);   // the mobile app's actions too

        // Login time limits: idle timeout and a maximum login length (config/session.php).
        $middleware->web(append: [\App\Http\Middleware\EnforceLoginTimeLimit::class]);

        // One login per portal (shop, Seller Centre, sorting center, courier, admin): each has its own
        // session cookie, and a login made in the wrong portal is handed to the right one (App\Support\Portal).
        $middleware->web(replace: [\Illuminate\Session\Middleware\StartSession::class => \App\Http\Middleware\StartPortalSession::class]);
        $middleware->web(append: [\App\Http\Middleware\PortalHandoff::class]);

        // After signing out, Back must not show a page of that account: no page shown to a signed-in
        // user is kept by the browser (the account areas already use "prevent.back" for every page).
        $middleware->web(append: [\App\Http\Middleware\PreventBackHistory::class . ':signed-in']);
        $middleware->redirectGuestsTo(fn () => \App\Support\Portal::loginUrl());

        $middleware->alias([
            'role'         => \App\Http\Middleware\RoleMiddleware::class,
            'is_admin'     => \App\Http\Middleware\IsAdmin::class,
            'is_sc'        => \App\Http\Middleware\IsSortingCenter::class,
            'buyer_only'   => \App\Http\Middleware\BuyerOnly::class,
            'seller.approved' => \App\Http\Middleware\EnsureSellerApproved::class,
            'prevent.back' => \App\Http\Middleware\PreventBackHistory::class,
            // Mobile app API (/api/v1)
            'api.session'       => \App\Http\Middleware\ApiWebSession::class,
            'api.buyer'         => \App\Http\Middleware\ApiBuyerOnly::class,
            'api.seller'        => \App\Http\Middleware\ApiSellerOnly::class,
            'api.courier'       => \App\Http\Middleware\ApiCourierOnly::class,
            'api.logistics'     => \App\Http\Middleware\ApiLogisticsOnly::class,
            'api.optional-auth' => \App\Http\Middleware\ApiOptionalAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The mobile app API always answers in JSON (validation errors, 401, 403, 404…).
        $exceptions->shouldRenderJsonWhen(fn (\Illuminate\Http\Request $request) => $request->is('api/v1/*') || $request->expectsJson());

        // A form left open past the 30-minute session (or after logging out in another tab):
        // instead of the bare "419 Page Expired", go back with what was typed and a fresh token.
        // (Laravel has already turned the TokenMismatchException into an HTTP 419 by now.)
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if (! $e->getPrevious() instanceof \Illuminate\Session\TokenMismatchException) {
                return null;
            }
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your session expired. Please reload the page and try again.'], 419);
            }
            if ($request->routeIs('logout') || str_ends_with($request->path(), 'logout')) {
                return redirect()->route('login');
            }

            return redirect()->back(fallback: route('login'))
                ->withInput($request->except(['_token', 'password', 'password_confirmation', 'current_password']))
                ->with('error', 'Your session expired, so that was not saved. Please try again.');
        });

        // The app's login ended because it is older than a week (config sanctum.expiration): say so, like the
        // website does, instead of a bare "Unauthenticated.".
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            $minutes = (int) config('sanctum.expiration');
            if (! $request->is('api/*') || ! $minutes || ! ($bearer = $request->bearerToken())) {
                return null;
            }
            $token = \Laravel\Sanctum\PersonalAccessToken::findToken($bearer);
            if (! $token || $token->created_at->gt(now()->subMinutes($minutes))) {
                return null;   // unknown or revoked token: the usual answer
            }

            return response()->json(['message' => 'For your security you were signed out after '
                . \App\Http\Middleware\EnforceLoginTimeLimit::duration($minutes) . '. Please log in again.'], 401);
        });
    })->create();
