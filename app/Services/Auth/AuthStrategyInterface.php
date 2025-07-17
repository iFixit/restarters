<?php

namespace App\Services\Auth;

use Symfony\Component\HttpFoundation\Response;

interface AuthStrategyInterface
{
    /**
     * Check if the user is authenticated
     */
    public function isAuthenticated(): bool;

    /**
     * Check if the user has given consent (if required)
     */
    public function hasConsent(): bool;

    /**
     * Check if consent is required for this auth strategy
     */
    public function requiresConsent(): bool;

    /**
     * Get the redirect response for unauthenticated users
     */
    public function getUnauthenticatedResponse(): Response;

    /**
     * Get the redirect response for users without consent
     */
    public function getConsentResponse(): Response;

    /**
     * Handle post-authentication tasks (like API token generation)
     */
    public function handlePostAuth($_, Response $response): Response;

    /**
     * Get the strategy name
     */
    public function getName(): string;
} 