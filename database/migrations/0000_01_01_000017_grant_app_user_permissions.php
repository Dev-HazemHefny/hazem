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

        $tables = [
            'tenants',
            'users',
            'tenant_signup_events',
            'tenant_sequences',
            'accounts',
            'subscription_plans',
            'customers',
            'subscriptions',
            'journal_entries',
            'invoices',
            'invoice_line_items',
            'payments',
            'journal_lines',
            'revenue_recognition_schedules',
            'audit_logs',
            'personal_access_tokens',
            'password_reset_tokens',
            'sessions',
        ];

        foreach ($tables as $table) {
            DB::statement("ALTER TABLE {$table} OWNER TO migrate_user");
        }

        DB::unprepared(<<<'SQL'
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO app_user;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO app_user;
GRANT EXECUTE ON FUNCTION app_current_tenant_id() TO app_user;
GRANT EXECUTE ON FUNCTION app_tenant_row_visible(uuid) TO app_user;
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
REVOKE EXECUTE ON FUNCTION app_tenant_row_visible(uuid) FROM app_user;
REVOKE EXECUTE ON FUNCTION app_current_tenant_id() FROM app_user;
REVOKE ALL ON ALL TABLES IN SCHEMA public FROM app_user;
REVOKE ALL ON ALL SEQUENCES IN SCHEMA public FROM app_user;
SQL);
    }
};
