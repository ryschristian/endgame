<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }
        $user = $user->fresh();
        $version = $request->session()->get('auth_version');
        if ($user?->is_active && $version === null && Auth::viaRemember()) {
            $request->session()->put('auth_version', $user->auth_version);
            $version = $user->auth_version;
        }
        if (! $user?->is_active || $version !== $user->auth_version) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->with('status', 'Please sign in to continue.');
        }
        Auth::setUser($user);
        return $next($request);
    }
}
