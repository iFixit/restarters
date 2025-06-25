<?php

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
            'serve' => true,
            'report' => false,
        ],

        'public_uploads' => [
            'driver' => 'local',
            'root'   => public_path() . '/uploads',
        ],

        's3_uploads' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'root' => env('AWS_UPLOADS_ROOT', 'uploads'),
            'cloudfront_url' => env('AWS_CLOUDFRONT_URL'),
        ],

        'uploads' => [
            'driver' => env('UPLOADS_DISK', 'local'),
            'root' => env('UPLOADS_DISK') === 's3' ? env('AWS_UPLOADS_ROOT', 'uploads') : public_path() . '/uploads',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],
    ],

];
