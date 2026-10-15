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

        $middleware->alias([
            'role'         => \App\Http\Middleware\RoleMiddleware::class,
            'is_admin'     => \App\Http\Middleware\IsAdmin::class,
            'is_sc'        => \App\Http\Middleware\IsSortingCenter::class,
            'buyer_only'   => \App\Http\Middleware\BuyerOnly::class,
            'seller.approved' => \App\Http\Middleware\EnsureSellerApproved::class,
            'prevent.back' => \App\Http\Middleware\PreventBackHistory::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
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
    })->create();
