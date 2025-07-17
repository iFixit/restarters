<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
        // First check if user is already authenticated in Laravel
        if (Auth::check()) {
            return true;
        }

        // Check if user has valid iFixit session
        $sessionCookie = request()->cookie('session');
        if (!$sessionCookie) {
            return false;
        }

        // Validate session with iFixit API
        $userData = $this->ifixitService->validateSession($sessionCookie);
        if (!$userData) {
            return false;
        }

        try {
            // Sync/create user from iFixit data
            $user = User::syncFromExternal($userData);
            
            // Log the user in
            Auth::login($user);
            
            Log::debug('iFixit user authenticated', [
                'user_id' => $user->id,
                'external_id' => $userData['userid'],
                'email' => $userData['login']
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to sync iFixit user', [
                'error' => $e->getMessage(),
                'user_data' => $userData
            ]);
            return false;
        }
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