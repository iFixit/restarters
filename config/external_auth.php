<?php

return [
    'base_url' => env('EXTERNAL_AUTH_BASE_URL', 'https://www.ifixit.com'),
    'api_url' => env('EXTERNAL_AUTH_API_URL', 'https://www.ifixit.com/api/2.0'),
    'session_cookie_name' => 'session',
    'session_cookie_length' => 32,
    'enabled' => env('EXTERNAL_AUTH_ENABLED', true),
]; 