<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Translation Site Configurations
    |--------------------------------------------------------------------------
    |
    | This array maps domains to their corresponding translation site folders.
    | Each site should have a matching folder in the lang directory.
    | 
    | Example: for a site 'ifixit', create lang/ifixit/en/ for English translations
    |
    */
    'sites' => [
        [
            'site' => 'ifixit',
            'domain' => 'restarters-dev.cominor.com'
        ],
        [
            'site' => 'ifixit',
            'domain' => 'localhost'
        ],
        [
            'site' => 'ifixit',
            'domain' => '127.0.0.1'
        ]
    ],
    
    /*
    |--------------------------------------------------------------------------
    | JavaScript Translation Groups
    |--------------------------------------------------------------------------
    |
    | Specify which translation groups should be available to JavaScript.
    | These translations will be loaded and shared with Vue components.
    |
    */
    'js_groups' => [
        'dashboard',
        'common',
        'auth',
        'landing',
        'groups',
        'events',
        'nav',
        'general',
    ],
]; 