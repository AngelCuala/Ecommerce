<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The web's courier portal rule for the app (role:courier): riders of a logistics company and approved
 * direct couriers. An applicant still waiting (courier_pending) only sees the application status.
 */
class ApiCourierOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user?->isCourierPending()) {
            return response()->json(['message' => 'Your courier application is still waiting for approval.', 'home' => 'courier_status'], 403);
        }
        if (! $user || ! $user->isCourier()) {
            return response()->json(['message' => 'This part of the app is for riders and couriers.'], 403);
        }

        return $next($request);
    }
}
