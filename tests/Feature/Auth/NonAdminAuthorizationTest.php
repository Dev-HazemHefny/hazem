<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithTenantApi;
use Tests\Concerns\RefreshDatabaseForTesting;
use Tests\TestCase;

class NonAdminAuthorizationTest extends TestCase
{
    use InteractsWithTenantApi, RefreshDatabaseForTesting;

    public function test_non_admin_user_cannot_create_plans(): void
    {
        $data = $this->registerTenant();
        $tenantId = $data['tenant']['id'];

        TenantContext::runAs($tenantId, function () use ($tenantId, $data) {
            $user = User::create([
                'tenant_id' => $tenantId,
                'name' => 'Read Only',
                'email' => 'readonly@test.com',
                'password' => Hash::make('SecurePass123!'),
                'role' => UserRole::User,
                'is_active' => true,
            ]);

            $token = app(\App\Services\Tenancy\AuthService::class)->issueToken($user);
            $this->authToken = $token;
        });

        $this->postJson('/api/v1/plans', [
            'name' => 'Blocked Plan',
            'price_cents' => 10000,
            'billing_interval' => 'monthly',
        ], $this->authHeaders())
            ->assertForbidden();
    }
}
