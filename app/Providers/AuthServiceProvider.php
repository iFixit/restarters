<?php

namespace App\Providers;

use App\Services\Auth\AuthStrategyManager;
use App\Services\Auth\AuthStrategyInterface;
use App\Services\Auth\LocalAuthStrategy;
use App\Services\Auth\iFixitAuthStrategy;
use App\Services\Auth\iFixitAuthService;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Register the iFixit auth service
        $this->app->singleton(iFixitAuthService::class, fn () => new iFixitAuthService());

        // Register the auth strategy manager as a singleton
        $this->app->singleton(AuthStrategyManager::class, fn () => new AuthStrategyManager());

        // Register auth strategies
        $this->app->bind(LocalAuthStrategy::class, fn () => new LocalAuthStrategy());

        $this->app->bind(iFixitAuthStrategy::class, fn () => new iFixitAuthStrategy($this->app->make(iFixitAuthService::class)));

        // Register the current auth strategy based on config
        $this->app->bind(AuthStrategyInterface::class, fn () => $this->app->make(AuthStrategyManager::class)->getStrategy());
    }
} 