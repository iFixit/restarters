<?php

namespace App\Providers;

use App\Guards\ExternalSessionGuard;
use App\Services\IFixitAuthService;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
        
        // Register the external session guard
        Auth::extend('external_session', function ($app, $name, array $config) {
            return new ExternalSessionGuard(
                $app['request'],
                $app[IFixitAuthService::class]
            );
        });
    }
} 