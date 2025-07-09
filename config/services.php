<?php

return [

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'discourse' => [
        // The route's URI that acts as the entrypoint for Discourse to start the SSO process.
        // Used by Discourse to route incoming logins.
        'route' => 'discourse/sso',

        // Secret string used to encrypt/decrypt SSO information,
        // be sure that it is 10 chars or longer
        'secret' => env('DISCOURSE_SECRET'),

        // Disable Discourse from sending welcome message
        'suppress_welcome_message' => 'true',

        // Where the Discourse form lives
        'url' => env('DISCOURSE_URL'),

        // User specific items
        // NOTE: The 'email' & 'external_id' are the only 2 required fields
        'user' => [
            'access' => 'email,external_id,name,username,avatar_url',
            'email' => 'email',
            'external_id' => 'external_id',
            'name' => 'name',
            'username' => 'username',
            'avatar_url' => 'avatar_url',
        ],
    ],

    'matomo' => [
        'enabled' => env('MATOMO_ENABLED', false),
        'url' => env('MATOMO_URL'),
        'site_id' => env('MATOMO_SITE_ID'),
    ],

    'sentry' => [
        'user_context' => env('SENTRY_USER_CONTEXT', false),
        'breadcrumb_level' => env('SENTRY_BREADCRUMB_LEVEL', 'error'),
        'breadcrumb_sql' => env('SENTRY_BREADCRUMB_SQL', false),
        'breadcrumb_sql_origin' => env('SENTRY_BREADCRUMB_SQL_ORIGIN', false),
        'breadcrumb_sql_bindings' => env('SENTRY_BREADCRUMB_SQL_BINDINGS', false),
    ],

    'wp' => [
        'url' => env('WP_URL'),
        'username' => env('WP_USERNAME'),
        'password' => env('WP_PASSWORD'),
    ],

    'geocoder' => [
        'cache' => env('GEOCODER_CACHE', false),
        'providers' => [
            'mapbox' => [
                'key' => env('MAPBOX_ACCESS_TOKEN'),
            ],
        ],
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

];
