<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sellers whose application isn't approved yet can't use the seller panel
 * (no products, no orders) — they're sent to their application status page.
 */
class EnsureSellerApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isSeller() && ! $user->isApproved()) {
            return redirect()->route('seller.status');
        }

        return $next($request);
    }
}
