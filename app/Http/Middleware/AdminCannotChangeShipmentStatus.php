<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An order's and parcel's status follow the process: seller → logistics company → rider. Admins can see
 * every step but not change one by hand (overrides, mark picked up, sort, assign, pickup approval, event
 * corrections). Put on those admin routes; the pages show the status read-only.
 */
class AdminCannotChangeShipmentStatus
{
    public const MESSAGE = 'Order and parcel statuses follow the process (seller → logistics → rider), so an admin can\'t change them.';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => self::MESSAGE], 403);
        }

        return back()->with('error', self::MESSAGE);
    }
}
