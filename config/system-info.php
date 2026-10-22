<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Access token
    |--------------------------------------------------------------------------
    |
    | Token expected in the "Authorization: Bearer <token>" header.
    | When no token is configured, the route responds 404 (disabled).
    |
    */

    'token' => env('SYSTEM_INFO_TOKEN'),

    'path' => env('SYSTEM_INFO_PATH', 'api/system-info'),

    /*
    |--------------------------------------------------------------------------
    | Deployed version
    |--------------------------------------------------------------------------
    |
    | Set when building the Docker image (ARG/ENV), since the .git
    | directory is not copied into the image.
    |
    */

    'version' => env('APP_VERSION'),

    'commit' => env('APP_COMMIT'),

    'build_date' => env('APP_BUILD_DATE'),

    /*
    |--------------------------------------------------------------------------
    | Composer packages whose version is reported
    |--------------------------------------------------------------------------
    |
    | Only packages that are actually installed appear in the response.
    |
    */

    'packages' => [
        'laravel/framework',
        'inertiajs/inertia-laravel',
        'livewire/livewire',
        'filament/filament',
        'laravel/sanctum',
        'laravel/socialite',
        'laravel/horizon',
        'laravel/mcp',
        'directorytree/ldaprecord-laravel',
    ],

    /*
    |--------------------------------------------------------------------------
    | Thresholds
    |--------------------------------------------------------------------------
    */

    // Below this percentage of free disk space, the status becomes "degraded".
    'disk_free_warning_percent' => 10,

    // Above this number of failed jobs, the status becomes "degraded".
    'failed_jobs_warning' => 0,

];
