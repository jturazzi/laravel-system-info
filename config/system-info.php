<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Jeton d'accès
    |--------------------------------------------------------------------------
    |
    | Jeton attendu dans l'en-tête "Authorization: Bearer <jeton>".
    | Sans jeton configuré, la route répond 404 (désactivée).
    |
    */

    'token' => env('SYSTEM_INFO_TOKEN'),

    'path' => env('SYSTEM_INFO_PATH', 'api/system-info'),

    /*
    |--------------------------------------------------------------------------
    | Version déployée
    |--------------------------------------------------------------------------
    |
    | Renseignées au build de l'image Docker (ARG/ENV), le dépôt .git
    | n'étant pas copié dans l'image.
    |
    */

    'version' => env('APP_VERSION'),

    'commit' => env('APP_COMMIT'),

    'build_date' => env('APP_BUILD_DATE'),

    /*
    |--------------------------------------------------------------------------
    | Paquets Composer dont la version est remontée
    |--------------------------------------------------------------------------
    |
    | Seuls les paquets réellement installés apparaissent dans la réponse.
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
    | Seuils
    |--------------------------------------------------------------------------
    */

    // En dessous de ce pourcentage d'espace libre, le statut passe à "degraded".
    'disk_free_warning_percent' => 10,

    // Au-delà de ce nombre de jobs en échec, le statut passe à "degraded".
    'failed_jobs_warning' => 0,

];
