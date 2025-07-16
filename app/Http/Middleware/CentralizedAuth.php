<?php

namespace App\Http\Middleware;

use App\Services\Auth\AuthStrategyManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CentralizedAuth
{
    private AuthStrategyManager $authManager;

    public function __construct(AuthStrategyManager $authManager)
    {
        $this->authManager = $authManager;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        $isOptionalAuth = $mode === 'optional';
        
        // For optional auth mode, use default strategy but skip authentication enforcement
        $authStrategy = $this->authManager->getStrategy($isOptionalAuth ? null : $mode);

        // Handle optional auth mode: allow public access but run auth logic if authenticated
        if ($isOptionalAuth) {
            return $this->handleOptionalAuth($request, $next, $authStrategy);
        }

        // Standard authentication flow - enforce authentication and consent
        return $this->handleRequiredAuth($request, $next, $authStrategy);
    }

    /**
     * Handle optional authentication: allow public access but run auth logic if user is authenticated
     */
    private function handleOptionalAuth(Request $request, Closure $next, $authStrategy): Response
    {
        // Check authentication before running controller logic
        // This allows controllers to see if user is authenticated and act accordingly
        $isAuthenticated = $authStrategy->isAuthenticated();

        $response = $next($request);
        
        if ($isAuthenticated) {
            return $authStrategy->handlePostAuth($request, $response);
        }
        
        return $response;
    }

    /**
     * Handle required authentication: enforce authentication and consent requirements
     */
    private function handleRequiredAuth(Request $request, Closure $next, $authStrategy): Response
    {
        // Check if user is authenticated
        if (!$authStrategy->isAuthenticated()) {
            return $authStrategy->getUnauthenticatedResponse();
        }

        // Check if consent is required and given
        if ($authStrategy->requiresConsent() && !$authStrategy->hasConsent()) {
            return $authStrategy->getConsentResponse();
        }

        // Process the request and handle post-authentication tasks
        $response = $next($request);
        return $authStrategy->handlePostAuth($request, $response);
    }
} 