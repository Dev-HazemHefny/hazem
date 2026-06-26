<?php

namespace Tests\Feature\Tenancy;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenantApi;
use Tests\TestCase;

class TenantRlsTest extends TestCase
{
    use InteractsWithTenantApi;

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->pgsqlIsAvailable()) {
            $this->markTestSkipped(
                'PostgreSQL (app_user) is not reachable. Start Docker (`docker compose up -d`) and ensure DB_PORT=5433 in .env.'
            );
        }

        Artisan::call('migrate:fresh', [
            '--database' => 'pgsql_migrate',
            '--force' => true,
        ]);
    }

    public function test_app_user_only_sees_rows_for_current_tenant(): void
    {
        $tenantA = (string) Str::uuid();
        $tenantB = (string) Str::uuid();
        $customerA = (string) Str::uuid();
        $customerB = (string) Str::uuid();
        $now = now();

        $migrate = DB::connection('pgsql_migrate');

        $migrate->table('tenants')->insert([
            ['id' => $tenantA, 'name' => 'Tenant A', 'slug' => 'tenant-a-'.Str::random(6), 'status' => 'active', 'settings' => json_encode(['timezone' => 'UTC']), 'created_at' => $now, 'updated_at' => $now],
            ['id' => $tenantB, 'name' => 'Tenant B', 'slug' => 'tenant-b-'.Str::random(6), 'status' => 'active', 'settings' => json_encode(['timezone' => 'UTC']), 'created_at' => $now, 'updated_at' => $now],
        ]);

        $migrate->table('customers')->insert([
            ['id' => $customerA, 'tenant_id' => $tenantA, 'name' => 'Customer A', 'email' => 'a@test.com', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['id' => $customerB, 'tenant_id' => $tenantB, 'name' => 'Customer B', 'email' => 'b@test.com', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $app = DB::connection('pgsql_app');

        $app->beginTransaction();
        $app->select('SELECT set_config(?, ?, true)', ['app.current_tenant', $tenantA]);

        $visibleForA = $app->table('customers')->pluck('id')->all();
        $this->assertSame([$customerA], $visibleForA);
        $this->assertSame(0, $app->table('customers')->where('id', $customerB)->count());

        $app->commit();

        $app->beginTransaction();
        $app->select('SELECT set_config(?, ?, true)', ['app.current_tenant', $tenantB]);

        $visibleForB = $app->table('customers')->pluck('id')->all();
        $this->assertSame([$customerB], $visibleForB);

        $app->commit();
    }

    public function test_app_user_query_without_tenant_context_fails_closed(): void
    {
        $tenantId = (string) Str::uuid();
        $customerId = (string) Str::uuid();
        $now = now();

        $migrate = DB::connection('pgsql_migrate');
        $migrate->table('tenants')->insert([
            'id' => $tenantId,
            'name' => 'Tenant Fail Closed',
            'slug' => 'fail-closed-'.Str::random(6),
            'status' => 'active',
            'settings' => json_encode(['timezone' => 'UTC']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $migrate->table('customers')->insert([
            'id' => $customerId,
            'tenant_id' => $tenantId,
            'name' => 'Customer',
            'email' => 'fail-closed@test.com',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $connection = DB::connection('pgsql_app');
        $connection->disconnect();
        $connection->statement("SELECT set_config('app.current_tenant', '', false)");

        try {
            $connection->table('customers')->count();
            $this->fail('Expected tenant-scoped query without context to fail.');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertTrue(true);
        } finally {
            while ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
            $connection->disconnect();
        }
    }

    public function test_authenticated_api_works_with_rls_context(): void
    {
        config([
            'database.default' => 'pgsql',
            'database.connections.pgsql.database' => env('DB_PGSQL_DATABASE', 'saas_subscriptions'),
        ]);
        DB::purge('pgsql');

        $this->registerTenant(['email' => 'rls-auth-'.uniqid().'@test.com']);

        $this->getJson('/api/v1/auth/me', $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['user', 'tenant']]);
    }

    protected function tearDown(): void
    {
        \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;

        parent::tearDown();
    }

    private function pgsqlIsAvailable(): bool
    {
        try {
            DB::connection('pgsql_app')->getPdo();

            return DB::connection('pgsql_migrate')->getPdo() !== null;
        } catch (\Throwable) {
            return false;
        }
    }
}
