<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE tenants ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE tenants FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
CREATE POLICY tenant_migrate_bypass ON tenants
    FOR ALL
    USING (current_user = 'migrate_user')
    WITH CHECK (current_user = 'migrate_user')
SQL);

        DB::statement(<<<'SQL'
CREATE POLICY tenant_app_select ON tenants
    FOR SELECT
    USING (
        current_user = 'migrate_user'
        OR id = NULLIF(current_setting('app.current_tenant', true), '')::uuid
    )
SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS tenant_app_select ON tenants');
        DB::statement('DROP POLICY IF EXISTS tenant_migrate_bypass ON tenants');
        DB::statement('ALTER TABLE tenants DISABLE ROW LEVEL SECURITY');
    }
};
