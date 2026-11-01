<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The web's seller panel rules for the app (role:seller + seller.approved): only sellers; a seller
 * whose application is still pending may only prepare products (drafts), like on the website.
 */
class ApiSellerOnly
{
    /** What a seller waiting for approval may use (the web's EnsureSellerApproved::PENDING_ALLOWED). */
    private const PENDING_ALLOWED = [
        'api.v1.seller.products', 'api.v1.seller.products.form', 'api.v1.seller.products.store',
        'api.v1.seller.products.show', 'api.v1.seller.products.update',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->isSeller()) {
            return response()->json(['message' => 'This part of the app is for sellers.'], 403);
        }

        if (! $user->isApproved()) {
            if ($user->isPending() && in_array($request->route()?->getName(), self::PENDING_ALLOWED, true)) {
                return $next($request);
            }
            return response()->json(['message' => 'Your seller application is not approved yet. You can see its status on the application page.',
                'home' => 'seller_status'], 403);
        }

        return $next($request);
    }
}
