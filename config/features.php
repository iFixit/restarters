<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | This file is for storing the configuration for feature flags in your
    | application. These values are loaded from environment variables.
    |
    */

    'workbench_integration' => env('FEATURE_WORKBENCH_INTEGRATION', false),
    'wiki_integration' => env('FEATURE__WIKI_INTEGRATION', false),
    'discourse_integration' => env('FEATURE__DISCOURSE_INTEGRATION', false),
]; 