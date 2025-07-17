<?php

namespace App\Services\Auth;

use InvalidArgumentException;

class AuthStrategyManager
{
    private array $strategies = [];
    private string $defaultStrategy;

    public function __construct()
    {
        $this->defaultStrategy = config('restarters.auth.strategy', 'local');
        $this->registerStrategies();
    }

    /**
     * Register all available auth strategies
     */
    private function registerStrategies(): void
    {
        $this->strategies = [
            'local' => LocalAuthStrategy::class,
            'ifixit' => iFixitAuthStrategy::class,
        ];
    }

    /**
     * Get the current auth strategy instance
     */
    public function getStrategy(?string $strategy = null): AuthStrategyInterface
    {
        $strategy ??= $this->defaultStrategy;

        if (!isset($this->strategies[$strategy])) {
            throw new InvalidArgumentException("Auth strategy '{$strategy}' not found");
        }

        return app($this->strategies[$strategy]);
    }

    public function isUsingIFixitAuth(): bool
    {
        return $this->defaultStrategy === 'ifixit';
    }

    /**
     * Get the default strategy name
     */
    public function getDefaultStrategy(): string
    {
        return $this->defaultStrategy;
    }

    /**
     * Set the default strategy
     */
    public function setDefaultStrategy(string $strategy): void
    {
        if (!isset($this->strategies[$strategy])) {
            throw new InvalidArgumentException("Auth strategy '{$strategy}' not found");
        }

        $this->defaultStrategy = $strategy;
    }

    /**
     * Get all available strategies
     */
    public function getAvailableStrategies(): array
    {
        return array_keys($this->strategies);
    }

    /**
     * Register a custom auth strategy
     */
    public function registerStrategy(string $name, string $className): void
    {
        if (!is_subclass_of($className, AuthStrategyInterface::class)) {
            throw new InvalidArgumentException("Class '{$className}' must implement AuthStrategyInterface");
        }

        $this->strategies[$name] = $className;
    }

    /**
     * Check if a strategy is available
     */
    public function hasStrategy(string $strategy): bool
    {
        return isset($this->strategies[$strategy]);
    }

    /**
     * Get login URL for current auth strategy
     */
    public function getLoginUrl(string $callbackUrl = null): string
    {        
        if ($this->isUsingIFixitAuth()) {
            $ifixitService = app(iFixitAuthService::class);
            return $ifixitService->getLoginUrl($callbackUrl ?: url('/dashboard'));
        }
        
        return url('/login');
    }

    /**
     * Get logout URL for current auth strategy
     */
    public function getLogoutUrl(string $callbackUrl = null): string
    {
        if ($this->isUsingIFixitAuth()) {
            $ifixitService = app(iFixitAuthService::class);
            return $ifixitService->getLogoutUrl($callbackUrl ?: url('/'));
        }
        
        return url('/logout');
    }

    /**
     * Get register URL for current auth strategy
     */
    public function getRegisterUrl(string $callbackUrl = null): string
    {
        if ($this->isUsingIFixitAuth()) {
            $ifixitService = app(iFixitAuthService::class);
            return $ifixitService->getRegisterUrl($callbackUrl ?: url('/dashboard'));
        }
        
        return url('/user/register');
    }

    /**
     * Handle logout for any auth strategy with session flushing
     */
    public function handleLogout(): \Illuminate\Http\RedirectResponse
    {
        $user = \Auth::user();
        $isExternalUser = $user && $user->isExternalUser();
        
        // Always logout from Laravel first
        \Auth::logout();
        
        // Always flush session
        session()->flush();
        
        // If user is from iFixit, redirect to iFixit logout
        if ($isExternalUser && $this->isUsingIFixitAuth()) {
            return redirect($this->getLogoutUrl(url('/login')));
        }

        return redirect('/login');
    }
} 