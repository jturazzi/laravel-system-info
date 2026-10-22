<?php

namespace Jturazzi\SystemInfo;

use Composer\InstalledVersions;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Queue\Failed\CountableFailedJobProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Throwable;

class SystemInfo
{
    public const SCHEMA_VERSION = 1;

    public const STATUS_OK = 'ok';

    public const STATUS_DEGRADED = 'degraded';

    public const STATUS_DOWN = 'down';

    /** @var list<array{check: string, level: string, message: string}> */
    private array $issues = [];

    public function __construct(private Application $app) {}

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $this->issues = [];

        $report = [
            'schema' => self::SCHEMA_VERSION,
            'generated_at' => now()->toIso8601String(),
            'app' => $this->application(),
            'runtime' => $this->runtime(),
            'packages' => $this->packages(),
            'database' => $this->database(),
            'cache' => $this->cache(),
            'queue' => $this->queue(),
            'storage' => $this->storage(),
        ];

        return ['status' => $this->status(), 'issues' => $this->issues] + $report;
    }

    /**
     * @return array<string, mixed>
     */
    private function application(): array
    {
        $maintenance = $this->app->isDownForMaintenance();

        if ($maintenance) {
            $this->issue('app', self::STATUS_DEGRADED, 'Application is in maintenance mode');
        }

        return [
            'name' => config('app.name'),
            'environment' => $this->app->environment(),
            'debug' => (bool) config('app.debug'),
            'url' => config('app.url'),
            'version' => $this->version(),
            'commit' => config('system-info.commit') ?: null,
            'build_date' => config('system-info.build_date') ?: null,
            'timezone' => config('app.timezone'),
            'locale' => config('app.locale'),
            'maintenance' => $maintenance,
        ];
    }

    private function version(): ?string
    {
        $version = config('system-info.version') ?: config('app.version');

        if ($version) {
            return (string) $version;
        }

        $root = InstalledVersions::getRootPackage()['pretty_version'] ?? null;

        // "dev-main" or "1.0.0+no-version-set" are meaningless: the commit is reported separately.
        return $root && ! Str::startsWith($root, 'dev-') && ! Str::contains($root, 'no-version-set') ? $root : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function runtime(): array
    {
        return [
            'php' => PHP_VERSION,
            'laravel' => $this->app->version(),
            'sapi' => PHP_SAPI,
            'server' => $_SERVER['SERVER_SOFTWARE'] ?? null,
            'os' => PHP_OS_FAMILY.' '.php_uname('r'),
            'hostname' => gethostname() ?: null,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => (int) ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'opcache' => function_exists('opcache_get_status') && (bool) (@opcache_get_status(false)['opcache_enabled'] ?? false),
            'extensions' => collect(get_loaded_extensions())->map(fn (string $e) => strtolower($e))->sort()->values()->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function packages(): array
    {
        return collect(config('system-info.packages', []))
            ->filter(fn (string $package) => InstalledVersions::isInstalled($package))
            ->mapWithKeys(fn (string $package) => [$package => InstalledVersions::getPrettyVersion($package)])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function database(): array
    {
        $name = config('database.default');
        $info = [
            'connection' => $name,
            'driver' => config("database.connections.{$name}.driver"),
            'ok' => false,
        ];

        try {
            $connection = DB::connection();
            $start = hrtime(true);
            $connection->select('select 1');
            $info['latency_ms'] = round((hrtime(true) - $start) / 1e6, 2);
            $info['ok'] = true;
            $info['version'] = $this->databaseVersion($connection->getServerVersion());
            $info['database'] = $info['driver'] === 'sqlite'
                ? basename((string) $connection->getDatabaseName())
                : $connection->getDatabaseName();
            $info['size_bytes'] = $this->databaseSize($connection);
        } catch (Throwable $e) {
            $info['error'] = $this->errorMessage($e);
            $this->issue('database', self::STATUS_DOWN, 'Database unreachable');

            return $info;
        }

        try {
            $migrator = $this->app->make('migrator');

            if ($migrator->repositoryExists()) {
                $files = $migrator->getMigrationFiles(array_merge([database_path('migrations')], $migrator->paths()));
                $pending = array_values(array_diff(array_keys($files), $migrator->getRepository()->getRan()));
                $info['pending_migrations'] = count($pending);

                if ($pending !== []) {
                    $this->issue('database', self::STATUS_DEGRADED, count($pending).' pending migration(s)');
                }
            } else {
                $info['pending_migrations'] = null;
                $this->issue('database', self::STATUS_DEGRADED, 'Migrations table missing');
            }
        } catch (Throwable $e) {
            $info['pending_migrations'] = null;
        }

        return $info;
    }

    private function databaseVersion(string $version): string
    {
        // MariaDB returns e.g. "11.8.6-MariaDB-ubu2404": keep the numeric part only.
        return preg_match('/^(\d+\.\d+\.\d+)/', $version, $m) ? $m[1] : $version;
    }

    private function databaseSize($connection): ?int
    {
        try {
            return match ($connection->getDriverName()) {
                'sqlite' => @filesize($connection->getDatabaseName()) ?: null,
                'mysql', 'mariadb' => (int) $connection->scalar(
                    'select sum(data_length + index_length) from information_schema.tables where table_schema = ?',
                    [$connection->getDatabaseName()]
                ),
                'pgsql' => (int) $connection->scalar('select pg_database_size(current_database())'),
                default => null,
            };
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function cache(): array
    {
        $info = ['store' => config('cache.default'), 'ok' => false];

        try {
            $key = 'system-info:'.Str::random(8);
            Cache::put($key, 'ok', 10);
            $info['ok'] = Cache::pull($key) === 'ok';
        } catch (Throwable $e) {
            $info['error'] = $this->errorMessage($e);
        }

        if (! $info['ok']) {
            $this->issue('cache', self::STATUS_DEGRADED, 'Cache unavailable');
        }

        return $info;
    }

    /**
     * @return array<string, mixed>
     */
    private function queue(): array
    {
        $info = ['connection' => config('queue.default'), 'pending' => null, 'failed' => null];

        try {
            $info['pending'] = Queue::connection()->size();
        } catch (Throwable $e) {
            // e.g. QUEUE_CONNECTION=database without a jobs table: dispatching would fail too.
            $info['error'] = $this->errorMessage($e);
            $this->issue('queue', self::STATUS_DEGRADED, 'Queue unavailable');
        }

        try {
            $failer = $this->app->make('queue.failer');

            if ($failer instanceof CountableFailedJobProvider) {
                $info['failed'] = $failer->count();

                if ($info['failed'] > (int) config('system-info.failed_jobs_warning', 0)) {
                    $this->issue('queue', self::STATUS_DEGRADED, $info['failed'].' failed job(s)');
                }
            }
        } catch (Throwable) {
            // No failed_jobs table: nothing to report.
        }

        return $info;
    }

    /**
     * @return array<string, mixed>
     */
    private function storage(): array
    {
        $path = storage_path();
        $free = @disk_free_space($path) ?: null;
        $total = @disk_total_space($path) ?: null;
        $freePercent = $free && $total ? round($free / $total * 100, 1) : null;

        $writable = is_writable(storage_path('framework')) && is_writable(storage_path('logs'));

        if (! $writable) {
            $this->issue('storage', self::STATUS_DEGRADED, 'Storage directory is not writable');
        }

        if ($freePercent !== null && $freePercent < (float) config('system-info.disk_free_warning_percent', 10)) {
            $this->issue('storage', self::STATUS_DEGRADED, "Low disk space ({$freePercent}% free)");
        }

        return [
            'writable' => $writable,
            'disk_free_bytes' => $free !== null ? (int) $free : null,
            'disk_total_bytes' => $total !== null ? (int) $total : null,
            'disk_free_percent' => $freePercent,
            'logs_size_bytes' => $this->directorySize(storage_path('logs')),
        ];
    }

    private function directorySize(string $path): ?int
    {
        if (! is_dir($path)) {
            return null;
        }

        $size = 0;

        foreach (glob($path.'/*.log') ?: [] as $file) {
            $size += (int) @filesize($file);
        }

        return $size;
    }

    private function status(): string
    {
        $levels = array_column($this->issues, 'level');

        return match (true) {
            in_array(self::STATUS_DOWN, $levels, true) => self::STATUS_DOWN,
            $levels !== [] => self::STATUS_DEGRADED,
            default => self::STATUS_OK,
        };
    }

    private function issue(string $check, string $level, string $message): void
    {
        $this->issues[] = ['check' => $check, 'level' => $level, 'message' => $message];
    }

    private function errorMessage(Throwable $e): string
    {
        // PDO messages may contain host/username: only expose the class and code.
        return class_basename($e).($e->getCode() ? ' ('.$e->getCode().')' : '');
    }
}
