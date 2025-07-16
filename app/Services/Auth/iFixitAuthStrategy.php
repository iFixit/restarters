<?php

namespace App\Services\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class iFixitAuthStrategy implements AuthStrategyInterface
{
    private bool $requiresApiToken;
    private iFixitAuthService $ifixitService;

    public function __construct(iFixitAuthService $ifixitService)
    {
        $this->requiresApiToken = config('restarters.auth.require_api_token', true);
        $this->ifixitService = $ifixitService;
    }

    public function isAuthenticated(): bool
    {
        // Check if user is authenticated with iFixit service
        return $this->ifixitService->isAuthenticated();
    }

    public function hasConsent(): bool
    {
        // iFixit users typically bypass consent
        return true;
    }

    public function requiresConsent(): bool
    {
        return false;
    }

    public function getUnauthenticatedResponse(): Response
    {
        // Redirect to iFixit login page with callback
        $callbackUrl = url('/dashboard');
        $loginUrl = $this->ifixitService->getLoginUrl($callbackUrl);
        
        return redirect($loginUrl);
    }

    public function getConsentResponse(): Response
    {
        return redirect()->back();
    }

    public function handlePostAuth($_, Response $response): Response {
        if (!$this->requiresApiToken || !Auth::check()) {
            return $response;
        }

        // Ensure API token for Vue client
        $token = Auth::user()->ensureAPIToken();

        // Add API token as cookie
        if (method_exists($response, 'withCookie')) {
            $response->withCookie(cookie()->forever('restarters_apitoken', $token, null, null, false, false));
        }

        return $response;
    }

    public function getName(): string
    {
        return 'ifixit';
    }
} 