<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class PostgresTenantSession
{
    public static function apply(string $tenantId): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::select('SELECT set_config(?, ?, true)', ['app.current_tenant', $tenantId]);
        }
    }
}
