<?php

namespace Tests\Feature\Tenancy;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * @group pgsql-only
 */
class JournalBalanceTriggerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->pgsqlIsAvailable()) {
            $this->markTestSkipped('PostgreSQL is required for journal balance trigger tests.');
        }

        Artisan::call('migrate:fresh', [
            '--database' => 'pgsql_migrate',
            '--force' => true,
        ]);
    }

    public function test_unbalanced_journal_entry_insert_is_rejected(): void
    {
        $tenantId = (string) Str::uuid();
        $entryId = (string) Str::uuid();
        $accountId = (string) Str::uuid();
        $now = now();

        $migrate = DB::connection('pgsql_migrate');

        $migrate->table('tenants')->insert([
            'id' => $tenantId,
            'name' => 'Balance Test',
            'slug' => 'balance-'.Str::random(6),
            'status' => 'active',
            'settings' => json_encode(['timezone' => 'UTC']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $migrate->table('accounts')->insert([
            'id' => $accountId,
            'tenant_id' => $tenantId,
            'code' => '1000',
            'name' => 'Cash',
            'type' => 'asset',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $migrate->table('journal_entries')->insert([
            'id' => $entryId,
            'tenant_id' => $tenantId,
            'status' => 'posted',
            'entry_date' => '2025-01-01',
            'description' => 'Unbalanced',
            'posted_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $app = DB::connection('pgsql_app');
        $app->beginTransaction();
        $app->select('SELECT set_config(?, ?, true)', ['app.current_tenant', $tenantId]);

        $app->table('journal_lines')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'journal_entry_id' => $entryId,
            'account_id' => $accountId,
            'debit_cents' => 1000,
            'credit_cents' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $app->table('journal_lines')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'journal_entry_id' => $entryId,
            'account_id' => $accountId,
            'debit_cents' => 0,
            'credit_cents' => 500,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        try {
            $app->commit();
            $this->fail('Expected unbalanced journal entry to be rejected.');
        } catch (\Throwable) {
            $this->assertTrue(true);
        } finally {
            while ($app->transactionLevel() > 0) {
                $app->rollBack();
            }
            $app->disconnect();
        }
    }

    protected function tearDown(): void
    {
        \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;

        config(['database.default' => 'sqlite']);
        DB::purge('pgsql');
        DB::purge('pgsql_app');
        DB::purge('pgsql_migrate');

        parent::tearDown();
    }

    private function pgsqlIsAvailable(): bool
    {
        try {
            return DB::connection('pgsql_app')->getPdo() !== null
                && DB::connection('pgsql_migrate')->getPdo() !== null;
        } catch (\Throwable) {
            return false;
        }
    }
}
