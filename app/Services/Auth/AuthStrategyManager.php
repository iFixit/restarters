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
} 