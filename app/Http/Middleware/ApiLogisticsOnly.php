<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** The web's sorting-center portal rule for the app (is_sc): logistics company accounts only. */
class ApiLogisticsOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== 'sorting_center') {
            return response()->json(['message' => 'This part of the app is for logistics companies.'], 403);
        }

        return $next($request);
    }
}
