<?php

return [

    'features' => [
        'discourse_integration' => env('FEATURE__DISCOURSE_INTEGRATION', true),
        'wordpress_integration' => env('FEATURE__WORDPRESS_INTEGRATION', true),
        'image_upload_enabled' => env('FEATURE__IMAGE_UPLOAD', false),
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
