<?php

namespace Tests\Concerns;

trait InteractsWithTenantApi
{
    protected ?string $authToken = null;

    protected ?string $tenantSlug = null;

    /**
     * @return array{tenant: array, user: array, token: array}
     */
    protected function registerTenant(array $overrides = []): array
    {
        $email = $overrides['email'] ?? 'admin-'.uniqid().'@test.com';

        $response = $this->postJson('/api/v1/auth/register-tenant', array_merge([
            'company_name' => 'Test Corp',
            'admin_name' => 'Test Admin',
            'email' => $email,
            'password' => 'SecurePass123!',
            'timezone' => 'UTC',
        ], $overrides));

        $response->assertCreated();

        $this->authToken = $response->json('data.token.access_token');
        $this->tenantSlug = $response->json('data.tenant.slug');

        return $response->json('data');
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->authToken];
    }
}
