<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\Concerns\RefreshDatabaseForTesting;
use Tests\TestCase;

class TenantRegistrationTest extends TestCase
{
    use RefreshDatabaseForTesting;

    public function test_register_tenant_creates_admin_and_returns_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register-tenant', [
            'company_name' => 'Acme Corp',
            'admin_name' => 'Admin User',
            'email' => 'admin@acme.com',
            'password' => 'SecurePass123!',
            'timezone' => 'UTC',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['tenant', 'user', 'token'],
            ]);

        $this->assertDatabaseHas('tenants', ['name' => 'Acme Corp']);
        $this->assertDatabaseHas('users', ['email' => 'admin@acme.com']);
        $this->assertDatabaseHas('accounts', ['code' => '1000']);
    }

    public function test_health_endpoint_is_public(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.checks.database.status', 'up')
            ->assertJsonMissingPath('data.environment');
    }
}
