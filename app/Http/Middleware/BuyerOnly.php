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
        // Courier and rider accounts work, they don't shop with the same login.
        if ($user && ($user->isCourier() || $user->isCourierPending())) {
            $home = $user->isCourierPending() ? 'courier.status' : ($user->courier?->isApproved() ? 'courier.dashboard' : 'courier.rider');
            return redirect()->route($home)
                ->with('error', 'Courier and rider accounts are for deliveries. Please sign in with a buyer account to shop.');
        }

        return $next($request);
    }
}
