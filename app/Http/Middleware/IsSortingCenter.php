<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IsSortingCenter
{
    public function handle(Request $request, Closure $next)
    {
        if (! auth()->check() || auth()->user()->role !== 'sorting_center') {
            abort(403, 'Unauthorized. Sorting Center access only.');
        }

        return $next($request);
    }
}
