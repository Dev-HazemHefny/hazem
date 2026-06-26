<?php

namespace Tests\Feature;

use Tests\Concerns\RefreshDatabaseForTesting;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabaseForTesting;

    public function test_health_endpoint_is_public_and_reports_all_checks(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'healthy')
            ->assertJsonStructure([
                'data' => [
                    'status',
                    'timestamp',
                    'checks' => [
                        'database' => ['status'],
                        'cache' => ['status'],
                        'queue' => ['status'],
                        'storage' => ['status'],
                    ],
                ],
            ])
            ->assertJsonPath('data.checks.database.status', 'up')
            ->assertJsonPath('data.checks.cache.status', 'up')
            ->assertJsonPath('data.checks.queue.status', 'up')
            ->assertJsonPath('data.checks.storage.status', 'up')
            ->assertJsonMissingPath('data.environment');
    }

    public function test_health_returns_503_when_database_is_down(): void
    {
        DB::shouldReceive('connection')->andThrow(new \RuntimeException('Connection refused'));

        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(503)
            ->assertJsonPath('data.status', 'unhealthy')
            ->assertJsonPath('data.checks.database.status', 'down');
    }
}
