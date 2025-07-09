<?php

namespace App\Providers;

use App\Services\S3Service;
use Illuminate\Support\ServiceProvider;

class S3ServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(S3Service::class, function ($app) {
            return new S3Service();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Any S3-related boot logic can go here
    }
} 