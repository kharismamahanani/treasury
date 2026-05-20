<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Require2FA
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin()) {
            return $next($request);
        }

        // Admin without a secret yet → force setup (except on 2FA routes themselves)
        if (! $user->google2fa_secret) {
            if (! $request->routeIs('admin.2fa.*')) {
                return redirect()->route('admin.2fa.setup');
            }
            return $next($request);
        }

        // Admin has a secret but hasn't verified this session yet
        if (! $request->session()->get('2fa_verified')) {
            if (! $request->routeIs('admin.2fa.*')) {
                return redirect()->route('admin.2fa.verify');
            }
        }

        return $next($request);
    }
}
