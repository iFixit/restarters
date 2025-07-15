<?php

namespace App\Guards;

use App\Models\User;
use App\Services\IFixitAuthService;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ExternalSessionGuard implements Guard
{
    use GuardHelpers;
    
    private Request $request;
    private IFixitAuthService $iFixitAuthService;
    
    public function __construct(Request $request, IFixitAuthService $iFixitAuthService)
    {
        $this->request = $request;
        $this->iFixitAuthService = $iFixitAuthService;
    }
    
    public function check(): bool
    {
        return $this->user() !== null;
    }
    
    public function user(): ?User
    {
        if ($this->user !== null) {
            \Log::info('ExternalSessionGuard: user already set', ['user' => $this->user]);
            return $this->user;
        }
        
        // Check if external auth is enabled
        if (!config('external_auth.enabled', true)) {
            return null;
        }
        
        $sessionCookie = $this->getSessionCookie();
        \Log::info('ExternalSessionGuard: session cookie', ['cookie' => $sessionCookie]);
        
        if (!$sessionCookie) {
            \Log::warning('ExternalSessionGuard: No session cookie found');
            return null;
        }
        
        $externalUserData = $this->iFixitAuthService->validateSession($sessionCookie);
        \Log::info('ExternalSessionGuard: external user data', ['data' => $externalUserData]);
        
        if (!$externalUserData) {
            \Log::warning('ExternalSessionGuard: Session cookie invalid');
            return null;
        }
        
        // Sync user data and return User model
        $this->user = User::syncFromExternal($externalUserData);
        \Log::info('ExternalSessionGuard: user synced', ['user' => $this->user]);
        
        return $this->user;
    }
    
    public function validate(array $credentials = []): bool
    {
        return $this->check();
    }
    
    private function getSessionCookie(): ?string
    {
        // Get the session cookie from request
        $sessionCookie = $this->request->cookie('session');
        
        // Validate that this is actually an iFixit session cookie (32 characters)
        // and not a Laravel encrypted session cookie
        if ($sessionCookie && strlen($sessionCookie) === 32 && ctype_alnum($sessionCookie)) {
            return $sessionCookie;
        }
        
        // If the session cookie is not valid, try to extract from Cookie header
        $cookieHeader = $this->request->header('Cookie');
        if ($cookieHeader) {
            return $this->extractSessionFromCookieHeader($cookieHeader);
        }
        
        return null;
    }
    
    private function extractSessionFromCookieHeader(string $cookieHeader): ?string
    {
        // Find all session cookies in the header
        if (preg_match_all('/session=([^;]+)/', $cookieHeader, $matches)) {
            foreach ($matches[1] as $sessionValue) {
                // Return the first valid iFixit session cookie (32 alphanumeric characters)
                if (strlen($sessionValue) === 32 && ctype_alnum($sessionValue)) {
                    return $sessionValue;
                }
            }
        }
        return null;
    }
} 