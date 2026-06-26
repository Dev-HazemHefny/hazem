<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class TenantContext
{
    private static ?string $tenantId = null;

    public static function id(): ?string
    {
        return self::$tenantId;
    }

    public static function set(?string $tenantId): void
    {
        self::$tenantId = $tenantId;
    }

    public static function runAs(string $tenantId, callable $callback): mixed
    {
        return DB::transaction(function () use ($tenantId, $callback) {
            PostgresTenantSession::apply($tenantId);

            $previous = self::$tenantId;
            self::$tenantId = $tenantId;

            try {
                return $callback();
            } finally {
                self::$tenantId = $previous;
            }
        });
    }
}
