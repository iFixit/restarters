<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class IFixitAuth
{
    public function handle($request, Closure $next)
    {
        // Log the authentication attempt
        Log::debug('iFixit Auth: Starting authentication check', [
            'path' => $request->path(),
            'method' => $request->method(),
            'has_session_cookie' => $request->hasCookie('session'),
        ]);

        // Check if external auth is enabled
        if (!config('external_auth.enabled', true)) {
            Log::warning('iFixit Auth: External authentication is disabled');
            return $this->redirectToLogin($request, 'External authentication is disabled');
        }

        // Check iFixit external session authentication
        if (Auth::guard('external_session')->check()) {
            $externalUser = Auth::guard('external_session')->user();
            
            Log::info('iFixit Auth: User authenticated successfully', [
                'user_id' => $externalUser->id,
                'external_username' => $externalUser->external_username,
                'external_user_id' => $externalUser->external_user_id
            ]);
            
            // Login the user to the default guard for session continuity
            Auth::login($externalUser);
            
            return $next($request);
        }

        // Authentication failed
        Log::debug('iFixit Auth: No valid iFixit session found, redirecting to login');
        
        return $this->redirectToLogin($request);
    }

    private function redirectToLogin($request, $message = 'Please log in with iFixit')
    {
        // Store the intended URL for redirect after login
        $intendedUrl = $request->fullUrl();
        
        // For API requests, return JSON error
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'login_url' => route('auth.ifixit.login', ['redirect' => $intendedUrl])
            ], 401);
        }
        
        // For web requests, redirect to iFixit login
        return redirect()->route('auth.ifixit.login', ['redirect' => $intendedUrl]);
    }
}
