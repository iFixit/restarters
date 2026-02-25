<?php

return [

    'features' => [
        'discourse_integration' => env('FEATURE__DISCOURSE_INTEGRATION', true),
        'wordpress_integration' => env('FEATURE__WORDPRESS_INTEGRATION', true),
        'image_upload_enabled' => env('FEATURE__IMAGE_UPLOAD', false),
        'matomo_integration' => env('FEATURE__MATOMO_INTEGRATION', false),
        'public_events_api' => env('FEATURE__PUBLIC_EVENTS_API', false),
    ],

    'auth' => [
        'strategy' => env('AUTH_STRATEGY', 'local'), // 'local', 'ifixit'
        'require_consent' => env('AUTH_REQUIRE_CONSENT', true), // Set to false for iFixit
        'require_api_token' => env('AUTH_REQUIRE_API_TOKEN', true),
    ],

    'wiki' => [
        'base_url' => env('WIKI_URL'),
        'cookie_prefix' => env('WIKI_COOKIE_PREFIX', 'wiki_db'),
    ],

    'repairdirectory' => [
        'base_url' => env('REPAIRDIRECTORY_URL'),
    ],

    'xref_types' => [
        'networks' => 7,
    ],

];
