<?php

namespace App\Services\System;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class HealthService
{
    /** @var list<string> */
    private const CRITICAL_CHECKS = ['database'];

    public function check(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'storage' => $this->checkStorage(),
        ];

        if (config('database.default') === 'pgsql') {
            $checks['database_rls'] = $this->checkDatabaseRls();
        }

        $status = $this->resolveOverallStatus($checks);

        return [
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'checks' => $this->publicCheckSummary($checks),
        ];
    }

    public function httpStatus(array $result): int
    {
        return match ($result['status']) {
            'healthy' => 200,
            'degraded' => 200,
            default => 503,
        };
    }

    /**
     * @param  array<string, array{status: string}>  $checks
     */
    private function resolveOverallStatus(array $checks): string
    {
        foreach (self::CRITICAL_CHECKS as $name) {
            if (($checks[$name]['status'] ?? 'down') !== 'up') {
                return 'unhealthy';
            }
        }

        foreach ($checks as $check) {
            if ($check['status'] === 'down') {
                return 'degraded';
            }
        }

        return 'healthy';
    }

    /**
     * @param  array<string, array<string, mixed>>  $checks
     * @return array<string, array{status: string}>
     */
    private function publicCheckSummary(array $checks): array
    {
        $summary = [];

        foreach ($checks as $name => $check) {
            $summary[$name] = ['status' => $check['status']];
        }

        return $summary;
    }

    private function checkDatabase(): array
    {
        $connection = config('database.default');
        $started = microtime(true);

        try {
            DB::connection()->select('SELECT 1 AS ok');

            return [
                'status' => 'up',
                'connection' => $connection,
                'latency_ms' => $this->latencyMs($started),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'down',
                'connection' => $connection,
                'message' => 'Database connection failed.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    private function checkDatabaseRls(): array
    {
        $started = microtime(true);

        try {
            $exists = DB::selectOne(
                "SELECT EXISTS (
                    SELECT 1 FROM pg_proc WHERE proname = 'app_tenant_row_visible'
                ) AS ok"
            );

            if (! ($exists->ok ?? false)) {
                return [
                    'status' => 'down',
                    'message' => 'RLS helper function app_tenant_row_visible() is missing.',
                ];
            }

            return [
                'status' => 'up',
                'latency_ms' => $this->latencyMs($started),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'down',
                'message' => 'RLS verification failed.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    private function checkCache(): array
    {
        $driver = config('cache.default');
        $key = 'health:'.Str::uuid();
        $started = microtime(true);

        try {
            Cache::put($key, 'ok', 10);

            if (Cache::get($key) !== 'ok') {
                return [
                    'status' => 'down',
                    'driver' => $driver,
                    'message' => 'Cache read/write verification failed.',
                ];
            }

            Cache::forget($key);

            return [
                'status' => 'up',
                'driver' => $driver,
                'latency_ms' => $this->latencyMs($started),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'down',
                'driver' => $driver,
                'message' => 'Cache is unavailable.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    private function checkQueue(): array
    {
        $connection = config('queue.default');
        $driver = config("queue.connections.{$connection}.driver", $connection);
        $started = microtime(true);

        try {
            if ($driver === 'sync') {
                return [
                    'status' => 'up',
                    'connection' => $connection,
                    'driver' => $driver,
                    'note' => 'Sync driver — jobs run inline.',
                ];
            }

            if ($driver === 'database') {
                $table = config("queue.connections.{$connection}.table", 'jobs');
                DB::connection(config("queue.connections.{$connection}.connection"))
                    ->table($table)
                    ->limit(1)
                    ->count();

                return [
                    'status' => 'up',
                    'connection' => $connection,
                    'driver' => $driver,
                    'latency_ms' => $this->latencyMs($started),
                ];
            }

            Queue::connection($connection)->size();

            return [
                'status' => 'up',
                'connection' => $connection,
                'driver' => $driver,
                'latency_ms' => $this->latencyMs($started),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'down',
                'connection' => $connection,
                'driver' => $driver,
                'message' => 'Queue connection failed.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    private function checkStorage(): array
    {
        $disk = config('filesystems.default', 'local');
        $path = 'health/'.Str::uuid().'.txt';
        $started = microtime(true);

        try {
            Storage::disk($disk)->put($path, 'ok');

            if (Storage::disk($disk)->get($path) !== 'ok') {
                return [
                    'status' => 'down',
                    'disk' => $disk,
                    'message' => 'Storage read/write verification failed.',
                ];
            }

            Storage::disk($disk)->delete($path);

            return [
                'status' => 'up',
                'disk' => $disk,
                'latency_ms' => $this->latencyMs($started),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'down',
                'disk' => $disk,
                'message' => 'Storage is unavailable.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    private function latencyMs(float $started): float
    {
        return round((microtime(true) - $started) * 1000, 2);
    }
}
