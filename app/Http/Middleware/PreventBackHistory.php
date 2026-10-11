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

        // Add cache control headers to prevent browser caching. Use the headers bag so
        // this also works for file downloads (StreamedResponse has no ->header()).
        $response->headers->set('Cache-Control', 'no-cache, no-store, max-age=0, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', 'Fri, 01 Jan 1990 00:00:00 GMT');

        return $response;
    }
}