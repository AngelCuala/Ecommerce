<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sellers whose application isn't approved yet can't use the seller panel
 * (no orders, vouchers or reports) — they're sent to their application status page.
 * A seller waiting for approval may prepare products, but only as drafts
 * (the product controller blocks publishing until the store is verified).
 */
class EnsureSellerApproved
{
    /** Pages a seller waiting for approval may open. */
    private const PENDING_ALLOWED = [
        'seller.books.index', 'seller.books.create', 'seller.books.store', 'seller.books.edit', 'seller.books.update',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isSeller() && ! $user->isApproved()) {
            if ($user->isPending() && in_array($request->route()?->getName(), self::PENDING_ALLOWED, true)) {
                return $next($request);
            }

            return redirect()->route('seller.status');
        }

        return $next($request);
    }
}
