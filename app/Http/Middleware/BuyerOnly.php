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

        if ($user && $user->isSeller()) {
            return redirect()->route('seller.dashboard')
                ->with('error', 'Seller accounts cannot access buyer pages.');
        }

        return $next($request);
    }
}
