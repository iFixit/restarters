<?php

return [

    'mailers' => [
        'mailgun' => [
            'transport' => 'mailgun',
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],
    ],

    'markdown' => [
        'theme' => 'default',

        'paths' => [
            resource_path('views/vendor/mail'),
        ],
    ],

    'logos' => [
        'fixitclinic' => [
            'file' => 'logo-fixitclinic.jpg',
            'width' => 147,
            'height' => 60,
            'alt' => 'iFixit Community',
        ],
        'base' => [
            'file' => 'restart_logo_complete_black1.png',
            'width' => 147,
            'height' => 40,
            'alt' => 'Restarters.net',
        ],
    ],

];
