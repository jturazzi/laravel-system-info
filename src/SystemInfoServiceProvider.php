<?php

namespace Anefloire\SystemInfo;

use Anefloire\SystemInfo\Console\SystemInfoCommand;
use Anefloire\SystemInfo\Http\Middleware\AuthorizeSystemInfo;
use Anefloire\SystemInfo\Http\SystemInfoController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class SystemInfoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/system-info.php', 'system-info');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/system-info.php' => config_path('system-info.php'),
        ], 'system-info-config');

        if ($this->app->runningInConsole()) {
            $this->commands([SystemInfoCommand::class]);
        }

        if (! $this->app->routesAreCached()) {
            // Hors groupe "web" : pas de session, pas de CSRF. Pas de throttle non plus :
            // il dépend du cache, et la route doit pouvoir signaler un cache en panne.
            Route::get(config('system-info.path'), SystemInfoController::class)
                ->middleware(AuthorizeSystemInfo::class)
                ->name('system-info');
        }
    }
}
