<?php

use App\Support\Credentials;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    |
    | Used to persist the fetched OpenAPI spec between invocations so the CLI
    | does not download it on every run. Stored as files under the Wethod
    | config directory.
    |
    */

    'default' => env('CACHE_STORE', 'file'),

    'stores' => [

        'file' => [
            'driver' => 'file',
            'path' => Credentials::cacheDir(),
        ],

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

    ],

    'prefix' => env('CACHE_PREFIX', 'wethod_cache'),

];
