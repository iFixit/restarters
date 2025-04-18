<?php

use Dotenv\Dotenv;

/**
 * Load environment variables based on environment setting
 * 
 * This file allows specifying environment-specific .env files
 * that override the base .env file.
 */

// First determine the environment from existing loaded variables or command line arguments
$environment = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? null;

// If environment is passed as a command line argument like --env=testing
if (isset($_SERVER['argv'])) {
    foreach ($_SERVER['argv'] as $arg) {
        if (preg_match('/^--env=(.+)$/', $arg, $matches)) {
            $environment = $matches[1];
        }
    }
}

// Default to local environment if none specified
$environment = $environment ?: 'local';

// Base path for loading environment files
$basePath = dirname(__DIR__);

// First load specific environment file if it exists
if ($environment && file_exists($basePath . '/.env.' . $environment)) {
    $dotenv = Dotenv::createImmutable($basePath, '.env.' . $environment);
    $dotenv->load();
}

// Then load base .env file to ensure all other variables are available
// Variables already set from the specific environment file will not be overwritten
if (file_exists($basePath . '/.env')) {
    $dotenv = Dotenv::createImmutable($basePath);
    $dotenv->load();
} 