<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\IFixitAuthService;

class ExternalAuth
{
    private IFixitAuthService $iFixitAuthService;
    
    public function __construct(IFixitAuthService $iFixitAuthService)
    {
        $this->iFixitAuthService = $iFixitAuthService;
    }
    
    public function handle(Request $request, Closure $next)
    {
        \Log::info('ExternalAuth middleware: Auth::check()', ['auth_check' => Auth::check()]);
        \Log::info('ExternalAuth middleware: Auth::guard(external_session)->check()', ['external_check' => Auth::guard('external_session')->check()]);

        // Check if external auth is enabled
        if (!config('external_auth.enabled', true)) {
            return $next($request);
        }
        
        // First check if user is already authenticated via regular auth
        if (Auth::check()) {
            return $next($request);
        }
        
        // Check if user is authenticated via iFixit
        if (Auth::guard('external_session')->check()) {
            return $next($request);
        }
        
        // Store the intended URL for redirect after login
        $intendedUrl = $request->fullUrl();
        
        // If this is an API request, return JSON error
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Unauthenticated',
                'login_url' => $this->iFixitAuthService->getLoginUrl($intendedUrl)
            ], 401);
        }
        
        // For web requests, redirect to login
        return redirect()->route('login', ['redirect' => $intendedUrl]);
    }
} 