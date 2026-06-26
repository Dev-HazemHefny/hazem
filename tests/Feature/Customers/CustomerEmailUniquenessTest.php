<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use App\Support\TenantContext;
use Tests\Concerns\InteractsWithTenantApi;
use Tests\Concerns\RefreshDatabaseForTesting;
use Tests\TestCase;

class CustomerEmailUniquenessTest extends TestCase
{
    use InteractsWithTenantApi, RefreshDatabaseForTesting;

    public function test_duplicate_email_in_same_tenant_is_rejected(): void
    {
        $data = $this->registerTenant();
        $tenantId = $data['tenant']['id'];

        TenantContext::runAs($tenantId, function () {
            Customer::create([
                'name' => 'First',
                'email' => 'dup@test.com',
                'status' => 'active',
            ]);
        });

        $this->postJson('/api/v1/customers', [
            'name' => 'Second',
            'email' => 'dup@test.com',
        ], $this->authHeaders())
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }
}
