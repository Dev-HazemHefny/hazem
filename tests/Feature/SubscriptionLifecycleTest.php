<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\Customer;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Tests\Concerns\RefreshDatabaseForTesting;
use Tests\Concerns\InteractsWithTenantApi;
use Tests\TestCase;

class SubscriptionLifecycleTest extends TestCase
{
    use InteractsWithTenantApi, RefreshDatabaseForTesting;

    public function test_cancel_at_period_end_keeps_active_status(): void
    {
        $data = $this->registerTenant();
        $tenantId = $data['tenant']['id'];
        $subscriptionId = null;

        TenantContext::runAs($tenantId, function () use (&$subscriptionId, $tenantId) {
            $plan = SubscriptionPlan::create([
                'tenant_id' => $tenantId,
                'name' => 'Gold',
                'price_cents' => 50000,
                'billing_interval' => 'monthly',
                'status' => 'active',
            ]);
            $customer = Customer::create(['name' => 'Eve', 'email' => 'eve@test.com', 'status' => 'active']);
            $subscription = Subscription::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'start_date' => '2025-01-01',
                'auto_renew' => true,
                'current_period_start' => '2025-01-01',
                'current_period_end' => '2025-01-31',
                'next_billing_at' => CarbonImmutable::now(),
            ]);
            $subscriptionId = $subscription->id;
        });

        $this->postJson("/api/v1/subscriptions/{$subscriptionId}/cancel", [
            'cancel_at_period_end' => true,
        ], $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.cancel_at_period_end', true);
    }

    public function test_immediate_cancel_sets_cancelled_status(): void
    {
        $data = $this->registerTenant();
        $tenantId = $data['tenant']['id'];
        $subscriptionId = null;

        TenantContext::runAs($tenantId, function () use (&$subscriptionId, $tenantId) {
            $plan = SubscriptionPlan::create([
                'tenant_id' => $tenantId,
                'name' => 'Bronze',
                'price_cents' => 10000,
                'billing_interval' => 'monthly',
                'status' => 'active',
            ]);
            $customer = Customer::create(['name' => 'Frank', 'email' => 'frank@test.com', 'status' => 'active']);
            $subscription = Subscription::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'start_date' => '2025-01-01',
                'auto_renew' => true,
                'current_period_start' => '2025-01-01',
                'current_period_end' => '2025-01-31',
                'next_billing_at' => CarbonImmutable::now(),
            ]);
            $subscriptionId = $subscription->id;
        });

        $this->postJson("/api/v1/subscriptions/{$subscriptionId}/cancel", [
            'cancel_at_period_end' => false,
        ], $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_change_plan_rejects_same_plan_via_api(): void
    {
        $data = $this->registerTenant();
        $tenantId = $data['tenant']['id'];
        $subscriptionId = null;
        $planId = null;

        TenantContext::runAs($tenantId, function () use (&$subscriptionId, &$planId, $tenantId) {
            $plan = SubscriptionPlan::create([
                'tenant_id' => $tenantId,
                'name' => 'Gold',
                'price_cents' => 50000,
                'billing_interval' => 'monthly',
                'status' => 'active',
            ]);
            $planId = $plan->id;
            $customer = Customer::create(['name' => 'Grace', 'email' => 'grace@test.com', 'status' => 'active']);
            $subscription = Subscription::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'start_date' => '2025-01-01',
                'auto_renew' => true,
                'current_period_start' => '2025-01-01',
                'current_period_end' => '2025-01-31',
                'next_billing_at' => CarbonImmutable::now(),
            ]);
            $subscriptionId = $subscription->id;
        });

        $this->postJson("/api/v1/subscriptions/{$subscriptionId}/change-plan", [
            'plan_id' => $planId,
        ], $this->authHeaders())
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'SAME_PLAN');
    }

    public function test_delete_subscription_soft_deletes_and_cancels(): void
    {
        $data = $this->registerTenant();
        $tenantId = $data['tenant']['id'];
        $subscriptionId = null;

        TenantContext::runAs($tenantId, function () use (&$subscriptionId, $tenantId) {
            $plan = SubscriptionPlan::create([
                'tenant_id' => $tenantId,
                'name' => 'Silver',
                'price_cents' => 25000,
                'billing_interval' => 'monthly',
                'status' => 'active',
            ]);
            $customer = Customer::create(['name' => 'Henry', 'email' => 'henry@test.com', 'status' => 'active']);
            $subscription = Subscription::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'start_date' => '2025-01-01',
                'auto_renew' => true,
                'current_period_start' => '2025-01-01',
                'current_period_end' => '2025-01-31',
                'next_billing_at' => CarbonImmutable::now(),
            ]);
            $subscriptionId = $subscription->id;
        });

        $this->deleteJson("/api/v1/subscriptions/{$subscriptionId}", [], $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->getJson("/api/v1/subscriptions/{$subscriptionId}", $this->authHeaders())
            ->assertNotFound();
    }

    public function test_delete_rejected_when_open_invoices_exist(): void
    {
        $data = $this->registerTenant();
        $tenantId = $data['tenant']['id'];
        $subscriptionId = null;

        TenantContext::runAs($tenantId, function () use (&$subscriptionId, $tenantId) {
            $plan = SubscriptionPlan::create([
                'tenant_id' => $tenantId,
                'name' => 'Platinum',
                'price_cents' => 75000,
                'billing_interval' => 'monthly',
                'status' => 'active',
            ]);
            $customer = Customer::create(['name' => 'Ivy', 'email' => 'ivy@test.com', 'status' => 'active']);
            $subscription = Subscription::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'start_date' => '2025-01-01',
                'auto_renew' => true,
                'current_period_start' => '2025-01-01',
                'current_period_end' => '2025-01-31',
                'next_billing_at' => CarbonImmutable::parse('2025-01-01'),
            ]);
            $subscriptionId = $subscription->id;

            app(\App\Services\Billing\BillingCycleService::class)->billSubscription($subscription);
        });

        $this->deleteJson("/api/v1/subscriptions/{$subscriptionId}", [], $this->authHeaders())
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'SUBSCRIPTION_HAS_OPEN_INVOICES');
    }
}
