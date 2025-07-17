<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;
use App\Services\Auth\AuthStrategyManager;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * This middleware ensures that API requests are properly authenticated by:
 * 1. Checking if user is already authenticated via session
 * 2. Trying centralized auth (iFixit, local, etc.)
 * 3. Falling back to API token authentication (Bearer header, cookie, query param)
 * 4. Ensuring authenticated users have API tokens and setting Authorization header
 */
class CustomApiTokenAuth
{
    private AuthStrategyManager $authManager;

    public function __construct(AuthStrategyManager $authManager)
    {
        $this->authManager = $authManager;
    }

    public function handle(Request $request, Closure $next)
    {
        // Step 1: Try to authenticate user
        $this->authenticateUser($request);
        
        // Step 2: If authenticated, ensure API token access
        if (Auth::check()) {
            $this->ensureApiAccess($request);
            return $next($request);
        }
        
        // Step 3: Return 401 if not authenticated
        return $this->unauthorized($request);
    }

    /**
     * Try to authenticate the user using available methods
     */
    private function authenticateUser(Request $request): void
    {
        if (Auth::check()) {
            return;
        }

        $authStrategy = $this->authManager->getStrategy();
        if ($authStrategy->isAuthenticated()) {
            return;
        }

        $token = $this->extractApiToken($request);
        if ($token) {
            $user = User::where('api_token', $token)->first();
            if ($user) {
                Auth::login($user);
            }
        }
    }

    /**
     * Ensure authenticated user has API access
     */
    private function ensureApiAccess(Request $request): void
    {
        $user = Auth::user();
        
        $token = $user->ensureAPIToken();
        
        // Set Authorization header for downstream middleware
        $request->headers->set('Authorization', "Bearer {$token}");
    }

    /**
     * Extract API token from request
     */
    private function extractApiToken(Request $request): ?string
    {
        $bearerToken = $request->bearerToken();
        if ($bearerToken) {
            return $bearerToken;
        }

        $cookieToken = $request->cookie('restarters_apitoken');
        if ($cookieToken) {
            return $cookieToken;
        }

        $queryToken = $request->query('api_token');
        if ($queryToken) {
            return $queryToken;
        }

        return null;
    }

    /**
     * Return unauthenticated response
     */
    private function unauthorized(Request $request)
    {
        Log::warning('API request failed authentication', [
            'path' => $request->path(),
            'method' => $request->method(),
            'has_session' => $request->hasSession(),
            'has_api_cookie' => $request->hasCookie('restarters_apitoken'),
        ]);

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Unauthenticated.'
            ], 401);
        }

        throw new AuthenticationException('Unauthenticated.', ['api']);
    }
} 