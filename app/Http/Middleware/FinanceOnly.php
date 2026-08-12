<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class FinanceOnly
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user() || ! in_array($request->user()->role, ['admin', 'staff'], true)) {
            abort(403, 'Access restricted to Finance staff.');
        }

        return $next($request);
    }
}
