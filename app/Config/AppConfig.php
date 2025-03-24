<?php

namespace App\Config;

use Illuminate\Support\Facades\Config;

class AppConfig
{
    /**
     * Get application URL
     */
    public static function getUrl(): string
    {
        return (string) config('app.url');
    }

    /**
     * Get application environment
     */
    public static function getEnvironment(): string
    {
        return (string) config('app.env');
    }

    /**
     * Check if application is in debug mode
     */
    public static function isDebugMode(): bool
    {
        return (bool) config('app.debug');
    }

    /**
     * Get application name
     */
    public static function getName(): string
    {
        return (string) config('app.name');
    }

    /**
     * Check if a feature is enabled
     */
    public static function isFeatureEnabled(string $feature): bool
    {
        return (bool) config("features.{$feature}", false);
    }

    /**
     * Get database configuration
     * 
     * @return array{connection: string, host: string, database: string}
     */
    public static function getDatabaseConfig(): array
    {
        return [
            'connection' => (string) config('database.default'),
            'host' => (string) config('database.connections.mysql.host'),
            'database' => (string) config('database.connections.mysql.database'),
        ];
    }

    /**
     * Get session configuration
     * 
     * @return array{driver: string, lifetime: int, secure: bool, domain: ?string}
     */
    public static function getSessionConfig(): array
    {
        return [
            'driver' => (string) config('session.driver'),
            'lifetime' => (int) config('session.lifetime'),
            'secure' => (bool) config('session.secure'),
            'domain' => config('session.domain'),
        ];
    }

    /**
     * Get external service URL
     */
    public static function getServiceUrl(string $service): ?string
    {
        return config("services.{$service}.url");
    }

    /**
     * Check if external service is enabled
     */
    public static function isServiceEnabled(string $service): bool
    {
        return (bool) config("services.{$service}.enabled", false);
    }
} 