<?php

namespace App\Config;

class EnvironmentConfig
{
    /**
     * Get all environment configurations
     */
    public static function all(): array
    {
        return [
            'app' => self::getAppConfig(),
            'database' => self::getDatabaseConfig(),
            'services' => self::getServicesConfig(),
            'features' => self::getFeaturesConfig(),
        ];
    }

    /**
     * Get application configuration
     */
    public static function getAppConfig(): array
    {
        return [
            'name' => config('app.name'),
            'env' => config('app.env'),
            'debug' => config('app.debug'),
            'url' => config('app.url'),
            'logo' => config('app.logo'),
            'show_branch' => config('app.show_branch'),
        ];
    }

    /**
     * Get database configuration
     */
    public static function getDatabaseConfig(): array
    {
        return [
            'connection' => config('database.default'),
            'host' => config('database.connections.mysql.host'),
            'port' => config('database.connections.mysql.port'),
            'database' => config('database.connections.mysql.database'),
        ];
    }

    /**
     * Get external services configuration
     */
    public static function getServicesConfig(): array
    {
        return [
            'discourse' => [
                'enabled' => config('services.discourse.enabled'),
                'url' => config('services.discourse.url'),
            ],
            'wiki' => [
                'enabled' => config('services.wiki.enabled'),
                'url' => config('services.wiki.url'),
            ],
            'google' => [
                'analytics_id' => config('services.google.analytics_id'),
                'tag_manager_id' => config('services.google.tag_manager_id'),
            ],
        ];
    }

    /**
     * Get feature flags configuration
     */
    public static function getFeaturesConfig(): array
    {
        return [
            'workbench_integration' => config('features.workbench_integration'),
            'wiki_integration' => config('features.wiki_integration'),
            'discourse_integration' => config('features.discourse_integration'),
        ];
    }
} 