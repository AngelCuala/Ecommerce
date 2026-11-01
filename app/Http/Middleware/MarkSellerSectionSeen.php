<?php

namespace App\Http\Middleware;

use App\Support\SellerBadges;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Opening a Seller Centre section clears its sidebar number (its notifications are marked seen). */
class MarkSellerSectionSeen
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && ! $request->expectsJson() && $request->user()
            && ($section = SellerBadges::sectionOf('/' . $request->path()))) {
            SellerBadges::markSeen($request->user(), $section);
        }

        return $next($request);
    }
}
