<?php

namespace Tests\Feature\Auth;

use Tests\Concerns\RefreshDatabaseForTesting;
use Tests\Concerns\InteractsWithTenantApi;
use Tests\TestCase;

class AuthLoginTest extends TestCase
{
    use InteractsWithTenantApi, RefreshDatabaseForTesting;

    public function test_login_requires_tenant_slug(): void
    {
        $email = 'login-'.uniqid().'@test.com';
        $data = $this->registerTenant(['email' => $email]);

        $this->postJson('/api/v1/auth/login', [
            'tenant_slug' => $data['tenant']['slug'],
            'email' => $email,
            'password' => 'SecurePass123!',
        ])->assertOk()->assertJsonPath('success', true);
    }

    public function test_login_with_wrong_tenant_slug_fails(): void
    {
        $this->registerTenant(['email' => 'tenant-a-'.uniqid().'@test.com']);

        $this->postJson('/api/v1/auth/login', [
            'tenant_slug' => 'non-existent-slug',
            'email' => 'any@test.com',
            'password' => 'SecurePass123!',
        ])->assertUnauthorized();
    }

    public function test_same_email_in_different_tenants_requires_correct_slug(): void
    {
        $email = 'shared-'.uniqid().'@test.com';

        $tenantA = $this->registerTenant([
            'email' => $email,
            'company_name' => 'Company Alpha',
        ]);

        $this->registerTenant([
            'email' => $email,
            'company_name' => 'Company Beta',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'tenant_slug' => $tenantA['tenant']['slug'],
            'email' => $email,
            'password' => 'SecurePass123!',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.email', $email);
    }
}
