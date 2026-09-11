<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        foreach ($roles as $role) {
            if ($request->user()->role === $role) {
                return $next($request);
            }
        }

        // Special: 'courier_any' allows both courier and admin
        if (in_array('courier_any', $roles)) {
            if (in_array($request->user()->role, ['courier', 'admin'])) {
                return $next($request);
            }
        }

        abort(403, 'You do not have permission to access this page.');
    }
}
