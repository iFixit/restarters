<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment configuration
require_once __DIR__ . '/load-environment.php';
Config\EnvironmentLoader::load();

// Create the .env file for webpack
$envContent = "# Generated from environment configuration files\n";
$envContent .= "# DO NOT EDIT DIRECTLY\n\n";

// Get all environment variables
$variables = Config\EnvironmentLoader::getLoadedVariables();

// Add all MIX_ prefixed variables and basic app variables
foreach ($variables as $key => $value) {
    // Include all MIX_ variables for webpack
    if (strpos($key, 'MIX_') === 0) {
        $envContent .= "{$key}={$value}\n";
    }
    
    // Include essential app variables
    if (in_array($key, [
        'APP_ENV', 
        'APP_URL',
        'APP_DEBUG',
        'APP_NAME',
    ])) {
        $envContent .= "{$key}={$value}\n";
    }
}

// Add features as MIX variables for frontend
foreach ($variables as $key => $value) {
    if (strpos($key, 'FEATURE_') === 0 || strpos($key, 'FEATURE__') === 0) {
        $mixKey = 'MIX_' . $key;
        $envContent .= "{$mixKey}={$value}\n";
    }
}

// Write to .env file
file_put_contents(__DIR__ . '/../.env', $envContent);

echo "Generated .env file for webpack\n"; 