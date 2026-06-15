<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OpenAPI specification
    |--------------------------------------------------------------------------
    |
    | The Wethod OpenAPI spec is turned into one command per endpoint. By
    | default it is fetched from the public URL and cached locally; point
    | this at a local file path to work fully offline (tests do this).
    |
    */

    'spec_url' => env('WETHOD_SPEC_URL', 'https://docs.wethod.com/specs/openapi.yaml'),

    'spec_cache_ttl' => (int) env('WETHOD_SPEC_CACHE_TTL', 86400),

    /*
    |--------------------------------------------------------------------------
    | API connection
    |--------------------------------------------------------------------------
    |
    | The base URL all requests are sent to, plus the credentials and headers
    | Wethod requires on every request. `token` and `company` are normally
    | set through `wethod configure`; environment variables override them.
    |
    */

    'base_url' => env('WETHOD_BASE_URL', 'https://api.wethod.com'),

    'token' => env('WETHOD_TOKEN'),

    'company' => env('WETHOD_COMPANY'),

    'version' => env('WETHOD_VERSION'),

    // Used when no version is configured via env or stored credentials.
    'default_version' => '2024-06-15',

];
