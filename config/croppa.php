<?php

return [

    'path'              => 'uploads',
    'host'              => null,
    'crops_dir'         => 'uploads',
    'src_disk'          => config('filesystems.default') === 's3' ? 's3' : 'public_uploads',
    'crops_disk'        => config('filesystems.default') === 's3' ? 's3' : 'public_uploads',
    'crops_are_remote'  => config('filesystems.default') === 's3',
    'url_prefix'        => config('filesystems.default') === 's3' ? '' : '/uploads',
    'quality'           => 95,
    'interlace'         => true,
    'upsize'            => true,
    'php_memory_limit'  => '128M',
    'max_width'         => 2000,
    'max_height'        => 2000,
    'ignore'            => [],
    'filters'           => [],
    'handlers'          => [
        'gd' => 'Bkwld\Croppa\Handlers\GD',
        'imagick' => 'Bkwld\Croppa\Handlers\Imagick',
    ],

];
