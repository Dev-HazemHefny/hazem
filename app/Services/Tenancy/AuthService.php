<?php

namespace App\Services\Tenancy;

use App\Exceptions\AuthenticationException;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    /**
     * Authenticate user within a tenant and issue a Sanctum API token.
     *
     * @param  array{tenant_slug: string, email: string, password: string}  $credentials
     * @return array{user: User, token: string}
     */
    public function login(array $credentials): array
    {
        $tenant = $this->findActiveTenantBySlug($credentials['tenant_slug']);

        if (! $tenant) {
            throw new AuthenticationException();
        }

        return TenantContext::runAs($tenant->id, function () use ($credentials, $tenant) {
            $user = User::query()
                ->where('tenant_id', $tenant->id)
                ->where('email', $credentials['email'])
                ->where('is_active', true)
                ->first();

            if (! $user || ! Hash::check($credentials['password'], $user->password)) {
                throw new AuthenticationException();
            }

            $token = $this->createApiToken($user);

            AuditLog::create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'action' => 'auth.login',
                'entity_type' => User::class,
                'entity_id' => $user->id,
                'payload' => ['email' => $user->email],
                'ip_address' => request()?->ip(),
            ]);

            return ['user' => $user->load('tenant'), 'token' => $token];
        });
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();

        AuditLog::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'action' => 'auth.logout',
            'entity_type' => User::class,
            'entity_id' => $user->id,
            'payload' => [],
            'ip_address' => request()?->ip(),
        ]);
    }

    public function issueToken(User $user): string
    {
        return $this->createApiToken($user);
    }

    private function createApiToken(User $user): string
    {
        $expiresAt = now()->addMinutes((int) config('sanctum.expiration', 1440));
        $plainTextToken = $user->generateTokenString();

        $accessToken = $user->tokens()->create([
            'name' => 'api-token',
            'token' => hash('sha256', $plainTextToken),
            'abilities' => [
                'tenant_id' => $user->tenant_id,
                'role' => $user->role->value,
            ],
            'expires_at' => $expiresAt,
            'tenant_id' => $user->tenant_id,
        ]);

        return $accessToken->getKey().'|'.$plainTextToken;
    }

    private function findActiveTenantBySlug(string $slug): ?Tenant
    {
        $connection = config('database.default') === 'pgsql' ? 'pgsql_migrate' : null;

        $query = $connection ? Tenant::on($connection) : Tenant::query();

        return $query
            ->where('slug', $slug)
            ->where('status', 'active')
            ->first();
    }
}
