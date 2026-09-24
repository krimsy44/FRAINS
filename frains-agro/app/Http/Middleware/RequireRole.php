<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        abort_unless($request->user()?->status === 'active' && in_array($request->user()?->role?->name, $roles, true), 403);
        if ($request->user()->role?->name === 'CUSTOMER') {
            abort_unless($request->user()->customer?->status === 'active', 403);
        }

        return $next($request);
    }
}
