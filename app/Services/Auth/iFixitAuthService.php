<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class iFixitAuthService
{
    private string $baseUrl = 'https://www.ifixit.com';
    private string $apiUrl = 'https://www.ifixit.com/api/2.0';
    
    /**
     * Validate session cookie against iFixit API
     */
    public function validateSession(string $sessionCookie): ?array
    {
        try {
            // Validate session cookie format (32 characters for iFixit-style)
            if (strlen($sessionCookie) !== 32) {
                return null;
            }
            
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Cookie' => "session={$sessionCookie}",
                'User-Agent' => 'RestartProject/1.0',
            ])->get("{$this->apiUrl}/user");
            
            if ($response->successful()) {
                $userData = $response->json();
                
                // Validate required fields
                if (!isset($userData['userid']) || !isset($userData['login'])) {
                    return null;
                }
                
                return $userData;
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('iFixit API validation failed', [
                'error' => $e->getMessage(),
                'session_length' => strlen($sessionCookie)
            ]);
            return null;
        }
    }
    
    /**
     * Get iFixit login URL with callback
     */
    public function getLoginUrl(string $callbackUrl): string
    {
        return "{$this->baseUrl}/login?last_page=" . urlencode($callbackUrl);
    }
    
    /**
     * Get iFixit logout URL with callback
     */
    public function getLogoutUrl(string $callbackUrl): string
    {
        return "{$this->baseUrl}/user/logout?last_page=" . urlencode($callbackUrl);
    }
    
    /**
     * Check if user is authenticated with iFixit
     */
    public function isAuthenticated(): bool
    {
        $sessionCookie = request()->cookie('session');
        if (!$sessionCookie) {
            return false;
        }
        
        $userData = $this->validateSession($sessionCookie);
        return $userData !== null;
    }
    
    /**
     * Get current user data from iFixit session
     */
    public function getCurrentUser(): ?array
    {
        $sessionCookie = request()->cookie('session');
        if (!$sessionCookie) {
            return null;
        }
        
        return $this->validateSession($sessionCookie);
    }
} 