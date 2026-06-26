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

        $migratePassword = addslashes(env('DB_MIGRATE_PASSWORD', env('DB_PASSWORD', 'migrate_password')));
        $appPassword = addslashes(env('DB_APP_PASSWORD', env('DB_PASSWORD', 'app_password')));

        DB::unprepared(<<<SQL
DO \$\$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'migrate_user') THEN
        CREATE ROLE migrate_user LOGIN PASSWORD '{$migratePassword}' NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS;
    END IF;

    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'app_user') THEN
        CREATE ROLE app_user LOGIN PASSWORD '{$appPassword}' NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS;
    END IF;
END
\$\$;
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION app_current_tenant_id()
RETURNS uuid AS $$
BEGIN
    IF current_setting('app.current_tenant', true) IS NULL
       OR current_setting('app.current_tenant', true) = '' THEN
        RAISE EXCEPTION 'tenant context not set';
    END IF;

    RETURN current_setting('app.current_tenant', true)::uuid;
END;
$$ LANGUAGE plpgsql STABLE;

CREATE OR REPLACE FUNCTION app_tenant_row_visible(row_tenant_id uuid)
RETURNS boolean AS $$
BEGIN
    IF current_user = 'migrate_user' THEN
        RETURN TRUE;
    END IF;

    IF current_setting('app.current_tenant', true) IS NULL
       OR current_setting('app.current_tenant', true) = '' THEN
        RAISE EXCEPTION 'tenant context not set';
    END IF;

    RETURN row_tenant_id = current_setting('app.current_tenant', true)::uuid;
END;
$$ LANGUAGE plpgsql STABLE;
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS app_tenant_row_visible(uuid)');
        DB::unprepared('DROP FUNCTION IF EXISTS app_current_tenant_id()');

        DB::unprepared(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (SELECT FROM pg_roles WHERE rolname = 'app_user') THEN
        DROP ROLE app_user;
    END IF;

    IF EXISTS (SELECT FROM pg_roles WHERE rolname = 'migrate_user') THEN
        DROP ROLE migrate_user;
    END IF;
END
$$;
SQL);
    }
};
