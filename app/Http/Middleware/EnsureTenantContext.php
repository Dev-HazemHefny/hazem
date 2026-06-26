<?php

namespace App\Http\Middleware;

use App\Exceptions\ForbiddenException;
use App\Support\PostgresTenantSession;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies authenticated user's tenant matches the bootstrapped RLS context.
 */
class EnsureTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->tenant_id) {
            return $next($request);
        }

        if (TenantContext::id() && TenantContext::id() !== $user->tenant_id) {
            throw new ForbiddenException('Tenant context mismatch.');
        }

        if ($request->attributes->get('tenant_bootstrapped')) {
            return $next($request);
        }

        $ownsTransaction = DB::transactionLevel() === 0;

        if ($ownsTransaction) {
            DB::beginTransaction();
            DB::select('SELECT 1');
        }

        try {
            PostgresTenantSession::apply($user->tenant_id);
            TenantContext::set($user->tenant_id);

            $response = $next($request);

            if (method_exists($response, 'prepare')) {
                $response->prepare($request);
            }

            if ($ownsTransaction) {
                DB::commit();
            }

            return $response;
        } catch (\Throwable $e) {
            if ($ownsTransaction) {
                DB::rollBack();
            }

            throw $e;
        } finally {
            TenantContext::set(null);

            if ($ownsTransaction && DB::connection()->getDriverName() === 'pgsql') {
                DB::disconnect();
            }
        }
    }
}
