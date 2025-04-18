<?php

use Dotenv\Dotenv;

require __DIR__.'/../vendor/autoload.php';

// Load testing environment variables from .env.testing which will take precedence over .env
$dotenv = Dotenv::createImmutable(__DIR__.'/../', '.env.testing');
$dotenv->load();

// Load base environment variables from .env to ensure all other variables are available
$dotenv = Dotenv::createImmutable(__DIR__.'/..');
$dotenv->load();