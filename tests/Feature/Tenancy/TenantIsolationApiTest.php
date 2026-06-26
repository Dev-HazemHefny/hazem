<?php

namespace Tests\Feature\Tenancy;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\TenantContext;
use Tests\Concerns\RefreshDatabaseForTesting;
use Tests\Concerns\InteractsWithTenantApi;
use Tests\TestCase;

class TenantIsolationApiTest extends TestCase
{
    use InteractsWithTenantApi, RefreshDatabaseForTesting;

    public function test_tenant_cannot_access_another_tenants_plan(): void
    {
        $tenantOne = $this->registerTenant(['email' => 'tenant-one-'.uniqid().'@test.com']);
        $planId = null;

        TenantContext::runAs($tenantOne['tenant']['id'], function () use (&$planId, $tenantOne) {
            $plan = SubscriptionPlan::create([
                'tenant_id' => $tenantOne['tenant']['id'],
                'name' => 'Gold',
                'price_cents' => 50000,
                'currency' => 'USD',
                'billing_interval' => 'monthly',
                'status' => 'active',
            ]);
            $planId = $plan->id;
        });

        $this->registerTenant(['email' => 'tenant-two-'.uniqid().'@test.com']);

        $this->getJson("/api/v1/plans/{$planId}", $this->authHeaders())
            ->assertNotFound();
    }

    public function test_tenant_cannot_access_another_tenants_customer(): void
    {
        $tenantOne = $this->registerTenant(['email' => 'tenant-one-'.uniqid().'@test.com']);
        $customerId = null;

        TenantContext::runAs($tenantOne['tenant']['id'], function () use (&$customerId, $tenantOne) {
            $customer = Customer::create([
                'tenant_id' => $tenantOne['tenant']['id'],
                'name' => 'Alice',
                'email' => 'alice@test.com',
                'status' => 'active',
            ]);
            $customerId = $customer->id;
        });

        $this->registerTenant(['email' => 'tenant-two-'.uniqid().'@test.com']);

        $this->getJson("/api/v1/customers/{$customerId}", $this->authHeaders())
            ->assertNotFound();
    }

    public function test_tenant_cannot_access_another_tenants_invoice(): void
    {
        $tenantOne = $this->registerTenant(['email' => 'tenant-one-'.uniqid().'@test.com']);
        $invoiceId = null;

        TenantContext::runAs($tenantOne['tenant']['id'], function () use (&$invoiceId, $tenantOne) {
            $plan = SubscriptionPlan::create([
                'tenant_id' => $tenantOne['tenant']['id'],
                'name' => 'Plan',
                'price_cents' => 10000,
                'currency' => 'USD',
                'billing_interval' => 'monthly',
                'status' => 'active',
            ]);
            $customer = Customer::create([
                'tenant_id' => $tenantOne['tenant']['id'],
                'name' => 'Bob',
                'email' => 'bob@test.com',
                'status' => 'active',
            ]);
            $subscription = Subscription::create([
                'tenant_id' => $tenantOne['tenant']['id'],
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'start_date' => '2025-01-01',
                'auto_renew' => true,
                'current_period_start' => '2025-01-01',
                'current_period_end' => '2025-01-31',
                'next_billing_at' => now(),
            ]);
            $invoice = Invoice::create([
                'tenant_id' => $tenantOne['tenant']['id'],
                'subscription_id' => $subscription->id,
                'customer_id' => $customer->id,
                'invoice_number' => 'INV-TEST-00001',
                'status' => 'open',
                'subtotal_cents' => 10000,
                'tax_cents' => 0,
                'total_cents' => 10000,
                'amount_paid_cents' => 0,
                'amount_due_cents' => 10000,
                'period_start' => '2025-01-01',
                'period_end' => '2025-01-31',
                'issued_at' => now(),
                'due_at' => now()->addDays(7),
                'billing_idempotency_key' => 'iso-'.uniqid(),
            ]);
            $invoiceId = $invoice->id;
        });

        $this->registerTenant(['email' => 'tenant-two-'.uniqid().'@test.com']);

        $this->getJson("/api/v1/invoices/{$invoiceId}", $this->authHeaders())
            ->assertNotFound();
    }

    public function test_tenant_cannot_pay_another_tenants_invoice(): void
    {
        $tenantOne = $this->registerTenant(['email' => 'tenant-one-'.uniqid().'@test.com']);
        $invoiceId = null;

        TenantContext::runAs($tenantOne['tenant']['id'], function () use (&$invoiceId, $tenantOne) {
            $plan = SubscriptionPlan::create([
                'tenant_id' => $tenantOne['tenant']['id'],
                'name' => 'Plan',
                'price_cents' => 10000,
                'billing_interval' => 'monthly',
                'status' => 'active',
            ]);
            $customer = Customer::create([
                'tenant_id' => $tenantOne['tenant']['id'],
                'name' => 'Pay Target',
                'email' => 'pay-target@test.com',
                'status' => 'active',
            ]);
            $subscription = Subscription::create([
                'tenant_id' => $tenantOne['tenant']['id'],
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'start_date' => '2025-01-01',
                'auto_renew' => true,
                'current_period_start' => '2025-01-01',
                'current_period_end' => '2025-01-31',
                'next_billing_at' => now(),
            ]);
            $invoice = Invoice::create([
                'tenant_id' => $tenantOne['tenant']['id'],
                'subscription_id' => $subscription->id,
                'customer_id' => $customer->id,
                'invoice_number' => 'INV-PAY-00001',
                'status' => 'open',
                'subtotal_cents' => 10000,
                'tax_cents' => 0,
                'total_cents' => 10000,
                'amount_paid_cents' => 0,
                'amount_due_cents' => 10000,
                'period_start' => '2025-01-01',
                'period_end' => '2025-01-31',
                'issued_at' => now(),
                'due_at' => now()->addDays(7),
                'billing_idempotency_key' => 'iso-pay-'.uniqid(),
            ]);
            $invoiceId = $invoice->id;
        });

        $this->registerTenant(['email' => 'tenant-two-'.uniqid().'@test.com']);

        $this->postJson("/api/v1/invoices/{$invoiceId}/payments", [
            'amount_cents' => 10000,
            'client_idempotency_key' => 'cross-tenant-pay',
        ], $this->authHeaders())->assertNotFound();
    }

    public function test_tenant_income_statement_excludes_other_tenant_revenue(): void
    {
        $tenantOne = $this->registerTenant(['email' => 'tenant-one-'.uniqid().'@test.com']);

        TenantContext::runAs($tenantOne['tenant']['id'], function () {
            $plan = SubscriptionPlan::create([
                'name' => 'Revenue Plan',
                'price_cents' => 25000,
                'billing_interval' => 'monthly',
                'status' => 'active',
            ]);
            $customer = Customer::create(['name' => 'A Customer', 'email' => 'a-rev@test.com', 'status' => 'active']);
            Subscription::create([
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
                'price_cents' => 25000,
                'billing_interval' => 'monthly',
                'status' => 'active',
                'start_date' => '2025-01-01',
                'auto_renew' => true,
                'current_period_start' => '2025-01-01',
                'current_period_end' => '2025-01-31',
                'next_billing_at' => \Carbon\CarbonImmutable::parse('2025-01-01'),
            ]);

            app(\App\Actions\RunBillingCycleAction::class)->execute(\Carbon\CarbonImmutable::parse('2025-01-01'));
            app(\App\Actions\RecognizeSubscriptionRevenueAction::class)->execute(\Carbon\CarbonImmutable::parse('2025-01-31'));
        });

        $this->registerTenant(['email' => 'tenant-two-'.uniqid().'@test.com']);

        $response = $this->getJson('/api/v1/reports/income-statement?from=2025-01-01&to=2025-01-31', $this->authHeaders());
        $response->assertOk();
        $this->assertSame(0, $response->json('data.subscription_revenue_cents'));
    }

    public function test_tenant_listing_only_shows_own_records(): void
    {
        $tenantOne = $this->registerTenant(['email' => 'tenant-one-'.uniqid().'@test.com']);

        TenantContext::runAs($tenantOne['tenant']['id'], function () use ($tenantOne) {
            Customer::create([
                'tenant_id' => $tenantOne['tenant']['id'],
                'name' => 'Tenant One Customer',
                'email' => 'one@test.com',
                'status' => 'active',
            ]);
        });

        $tenantTwo = $this->registerTenant(['email' => 'tenant-two-'.uniqid().'@test.com']);

        TenantContext::runAs($tenantTwo['tenant']['id'], function () use ($tenantTwo) {
            Customer::create([
                'tenant_id' => $tenantTwo['tenant']['id'],
                'name' => 'Tenant Two Customer',
                'email' => 'two@test.com',
                'status' => 'active',
            ]);
        });

        $response = $this->getJson('/api/v1/customers', $this->authHeaders());
        $response->assertOk();

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertSame(['Tenant Two Customer'], $names);
    }

    public function test_user_query_without_tenant_context_returns_no_rows(): void
    {
        $data = $this->registerTenant();
        $tenantId = $data['tenant']['id'];

        TenantContext::runAs($tenantId, function () {
            $this->assertGreaterThan(0, User::count());
        });

        TenantContext::set(null);
        $this->assertSame(0, User::count());
    }
}
