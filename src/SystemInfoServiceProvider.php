<?php

namespace Jturazzi\SystemInfo;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Jturazzi\SystemInfo\Console\SystemInfoCommand;
use Jturazzi\SystemInfo\Http\Middleware\AuthorizeSystemInfo;
use Jturazzi\SystemInfo\Http\SystemInfoController;

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
            // Outside the "web" group: no session, no CSRF. No throttle either:
            // it relies on the cache, and this route must be able to report a broken cache.
            Route::get(config('system-info.path'), SystemInfoController::class)
                ->middleware(AuthorizeSystemInfo::class)
                ->name('system-info');
        }
    }
}
