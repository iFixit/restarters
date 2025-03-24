<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class EnvironmentServiceProvider extends ServiceProvider
{
    /**
     * Required environment variables that must be set
     */
    protected $required = [
        'APP_KEY',
        'APP_URL',
        'DB_HOST',
        'DB_DATABASE',
        'DB_USERNAME',
        'DB_PASSWORD',
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->validateEnvironment();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Validate that all required environment variables are set
     */
    protected function validateEnvironment(): void
    {
        $missing = [];

        foreach ($this->required as $key) {
            if (empty(env($key))) {
                $missing[] = $key;
            }
        }

        if (!empty($missing)) {
            throw new \RuntimeException(
                'Required environment variables are not set: ' . implode(', ', $missing)
            );
        }
    }
} 