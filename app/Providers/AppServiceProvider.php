<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        if ($this->app->runningInConsole() && $this->isMigrateCommand()) {
            config(['database.default' => 'pgsql_migrate']);
        }

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        RateLimiter::for('jobs', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
    }

    private function isMigrateCommand(): bool
    {
        $argv = $_SERVER['argv'] ?? [];

        return isset($argv[1]) && in_array($argv[1], ['migrate', 'migrate:fresh', 'migrate:refresh', 'migrate:reset'], true);
    }
}
