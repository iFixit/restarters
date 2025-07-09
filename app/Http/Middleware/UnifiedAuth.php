<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class UnifiedAuth
{
    public function handle($request, Closure $next)
    {
        // If authenticated via regular session
        if (Auth::check()) {
            return $next($request);
        }

        // If authenticated via iFixit external session
        if (Auth::guard('external_session')->check()) {
            // Optionally, you can "login" the user to the default guard for session continuity
            Auth::login(Auth::guard('external_session')->user());
            \Log::info('UnifiedAuth middleware: user authenticated', ['user' => Auth::user()]);
            return $next($request);
        }

        // Not authenticated, redirect to login
        return redirect()->route('login', ['redirect' => $request->fullUrl()]);
    }
}
