<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventBackHistory
{
    /**
     * Handle an incoming request.
     * 
     * This middleware prevents users from accessing cached pages
     * when using browser back/forward buttons after logout.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Add cache control headers to prevent browser caching
        return $response->header('Cache-Control', 'no-cache, no-store, max-age=0, must-revalidate')
                       ->header('Pragma', 'no-cache')
                       ->header('Expires', 'Fri, 01 Jan 1990 00:00:00 GMT');
    }
}