<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BuyerOnly
{
    /**
     * Block seller (and admin/courier) accounts from accessing buyer-only pages.
     * Sellers are redirected to their seller dashboard.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Sellers and admins only use their own panels — never the storefront.
        if ($user && $user->isSeller()) {
            return redirect()->route($user->isApproved() ? 'seller.dashboard' : 'seller.status');
        }
        if ($user && $user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return $next($request);
    }
}
