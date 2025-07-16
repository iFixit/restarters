<?php

namespace App\Services\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class LocalAuthStrategy implements AuthStrategyInterface
{
    private bool $requiresConsent;
    private bool $requiresApiToken;

    public function __construct()
    {
        $this->requiresConsent = config('restarters.auth.require_consent', true);
        $this->requiresApiToken = config('restarters.auth.require_api_token', true);
    }

    public function isAuthenticated(): bool
    {
        return Auth::check() && Auth::user();
    }

    public function hasConsent(): bool
    {
        if (!$this->requiresConsent) {
            return true;
        }

        return Auth::check() && Auth::user() && Auth::user()->hasUserGivenConsent();
    }

    public function requiresConsent(): bool
    {
        return $this->requiresConsent;
    }

    public function getUnauthenticatedResponse(): Response
    {
        return redirect()->guest(route('login'));
    }

    public function getConsentResponse(): Response
    {
        return redirect('/user/register');
    }

    public function handlePostAuth($_, Response $response): Response
    {
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
        return 'local';
    }
} 