<?php

namespace Config;

use Dotenv\Dotenv;
use RuntimeException;

class EnvironmentLoader
{
    protected static $loaded = false;
    protected static $envPath = __DIR__ . '/environments';

    /**
     * Load environment configuration
     */
    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        // Load base environment
        self::loadEnvFile('base.env');

        // Load environment-specific configuration
        $environment = $_ENV['APP_ENV'] ?? 'local';
        self::loadEnvFile("{$environment}.env");

        // Load features
        self::loadEnvFile('features.env');

        // Load secrets last to allow overrides
        self::loadEnvFile('secrets.env');

        self::$loaded = true;
    }

    /**
     * Load specific environment file
     */
    protected static function loadEnvFile(string $filename): void
    {
        $path = self::$envPath . '/' . $filename;

        if (!file_exists($path)) {
            throw new RuntimeException("Environment file {$filename} not found");
        }

        $dotenv = Dotenv::createImmutable(dirname($path), basename($path));
        $dotenv->load();
    }

    /**
     * Get all loaded environment variables
     */
    public static function getLoadedVariables(): array
    {
        return $_ENV;
    }
} 