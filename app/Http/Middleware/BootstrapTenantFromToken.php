<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use App\Support\PostgresTenantSession;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets PostgreSQL RLS context from the Sanctum bearer token before auth:sanctum loads User.
 */
class BootstrapTenantFromToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainTextToken = $request->bearerToken();

        if (! $plainTextToken) {
            return $next($request);
        }

        $tenantId = $this->resolveTenantIdFromToken($plainTextToken);

        if (! $tenantId) {
            return $next($request);
        }

        $ownsTransaction = DB::transactionLevel() === 0;

        if ($ownsTransaction) {
            DB::beginTransaction();
            DB::select('SELECT 1');
        }

        try {
            PostgresTenantSession::apply($tenantId);
            TenantContext::set($tenantId);
            $request->attributes->set('tenant_bootstrapped', true);

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

    private function resolveTenantIdFromToken(string $bearerToken): ?string
    {
        $token = PersonalAccessToken::findToken($bearerToken);

        if (! $token) {
            return null;
        }

        if ($token->expires_at !== null && $token->expires_at->isPast()) {
            return null;
        }

        if ($token->tenant_id) {
            return $token->tenant_id;
        }

        $abilities = is_string($token->abilities)
            ? json_decode($token->abilities, true)
            : $token->abilities;

        return is_array($abilities) ? ($abilities['tenant_id'] ?? null) : null;
    }
}
