<?php

namespace App\Database\Migrations\Concerns;

use Illuminate\Support\Facades\DB;

trait EnablesRowLevelSecurity
{
    protected function enableTenantRls(string $table): void
    {
        if (\Illuminate\Support\Facades\DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
        DB::statement("CREATE POLICY tenant_isolation ON {$table} USING (app_tenant_row_visible(tenant_id))");
    }

    protected function disableTenantRls(string $table): void
    {
        if (\Illuminate\Support\Facades\DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
        DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
    }
}
