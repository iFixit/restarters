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
    | This configuration is no longer used directly. The App\Services\TranslationService
    | class now dynamically determines which translation groups should be available
    | to JavaScript based on the files in the lang/en directory.
    |
    | You can customize the included/excluded groups by modifying the TranslationService.
    |
    */
]; 