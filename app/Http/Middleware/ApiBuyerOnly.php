<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** The web's BuyerOnly for the app: sellers and admins use their own panels, never the storefront. */
class ApiBuyerOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ($user->isSeller() || $user->isAdmin())) {
            return response()->json(['message' => $user->isSeller()
                ? 'Seller accounts use the seller pages. Please sign in with a buyer account to shop.'
                : 'Admin accounts use the admin panel on the website.'], 403);
        }
        // Courier and rider accounts work, they don't shop with the same login.
        if ($user && ($user->isCourier() || $user->isCourierPending())) {
            return response()->json(['message' => 'Courier and rider accounts are for deliveries. Please sign in with a buyer account to shop.'], 403);
        }

        return $next($request);
    }
}
