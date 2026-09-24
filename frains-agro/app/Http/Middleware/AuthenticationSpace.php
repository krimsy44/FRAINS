<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticationSpace
{
    public function handle(Request $request, Closure $next)
    {
        $guard = $request->is('admin', 'admin/*') ? 'admin' : 'web';
        Auth::shouldUse($guard);
        if ($guard === 'web' && Auth::check() && Auth::user()->role?->name !== 'CUSTOMER') {
            // Retire a management login left in the former shared guard.
            Auth::guard('web')->logout();
        }

        if ($guard === 'web' && Auth::check() && Auth::user()->customer?->account_deleted_at) {
            Auth::guard('web')->logout();
        }

        // Each space remembers its own destination after authentication.
        $key = 'auth.intended.'.$guard;
        $request->session()->forget('url.intended');
        if ($request->session()->has($key)) {
            $request->session()->put('url.intended', $request->session()->get($key));
        }

        try {
            return $next($request);
        } catch (\Illuminate\Auth\AuthenticationException $exception) {
            if ($request->isMethod('GET') && ! $request->expectsJson()) {
                $request->session()->put('url.intended', $request->fullUrl());
            }
            throw $exception;
        } finally {
            if ($request->session()->has('url.intended')) {
                $request->session()->put($key, $request->session()->pull('url.intended'));
            } else {
                $request->session()->forget($key);
            }
        }
    }
}
