<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class TenantRouteBinding
{
    public static function resolve(string $modelClass, string $id): Model
    {
        $tenantId = TenantContext::id() ?? Auth::user()?->tenant_id;

        abort_unless($tenantId, 404);

        /** @var Model $modelClass */
        return $modelClass::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);
    }
}
