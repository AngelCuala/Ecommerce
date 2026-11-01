<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pages anyone can open (home, shop, a product, a store): with a valid app token the web knows the
 * buyer (wishlist hearts, claimed vouchers, purchase limits); without one it answers as for a guest.
 */
class ApiOptionalAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() && Auth::guard('sanctum')->user()) {
            Auth::shouldUse('sanctum');
        }

        return $next($request);
    }
}
