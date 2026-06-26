<?php

namespace App\Models\Concerns;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;

trait AppliesTenantScope
{
    protected static function applyTenantGlobalScope(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $tenantId = TenantContext::id();

            if ($tenantId) {
                $builder->where($builder->getModel()->getTable().'.tenant_id', $tenantId);
            } else {
                $builder->whereRaw('1 = 0');
            }
        });
    }
}
