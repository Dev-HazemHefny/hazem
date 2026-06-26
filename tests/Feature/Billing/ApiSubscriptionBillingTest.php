<?php

namespace Tests\Feature\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\JournalLine;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\Billing\BillingCycleService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Tests\Concerns\InteractsWithTenantApi;
use Tests\Concerns\RefreshDatabaseForTesting;
use Tests\TestCase;

class ApiSubscriptionBillingTest extends TestCase
{
    use InteractsWithTenantApi, RefreshDatabaseForTesting;

    public function test_api_subscription_sets_period_start_billing_and_creates_first_invoice(): void
    {
        $data = $this->registerTenant();
        $tenantId = $data['tenant']['id'];

        $customerId = null;
        $planId = null;

        TenantContext::runAs($tenantId, function () use ($tenantId, &$customerId, &$planId) {
            $customer = Customer::create([
                'name' => 'API Customer',
                'email' => 'api-customer@test.com',
                'status' => 'active',
            ]);
            $customerId = $customer->id;

            $plan = SubscriptionPlan::create([
                'tenant_id' => $tenantId,
                'name' => 'Starter',
                'price_cents' => 30000,
                'currency' => 'USD',
                'billing_interval' => 'monthly',
                'status' => 'active',
            ]);
            $planId = $plan->id;
        });

        $response = $this->postJson('/api/v1/subscriptions', [
            'customer_id' => $customerId,
            'plan_id' => $planId,
            'start_date' => '2025-01-01',
            'auto_renew' => true,
        ], $this->authHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.current_period_start', '2025-01-01')
            ->assertJsonPath('data.current_period_end', '2025-01-31')
            ->assertJsonPath('data.price_cents', 30000);

        $subscriptionId = $response->json('data.id');

        TenantContext::runAs($tenantId, function () use ($subscriptionId) {
            $subscription = Subscription::findOrFail($subscriptionId);
            $this->assertSame('2025-01-01', $subscription->next_billing_at->utc()->toDateString());

            app(BillingCycleService::class)->billSubscription($subscription);

            $invoice = Invoice::where('subscription_id', $subscriptionId)->first();
            $this->assertNotNull($invoice);
            $this->assertSame(30000, $invoice->total_cents);
            $this->assertNotNull($invoice->journal_entry_id);
        });
    }

    public function test_cancel_at_period_end_still_invoices_final_period(): void
    {
        $data = $this->registerTenant();
        $tenantId = $data['tenant']['id'];
        $subscriptionId = null;

        TenantContext::runAs($tenantId, function () use ($tenantId, &$subscriptionId) {
            $plan = SubscriptionPlan::create([
                'tenant_id' => $tenantId,
                'name' => 'Pro',
                'price_cents' => 50000,
                'billing_interval' => 'monthly',
                'status' => 'active',
            ]);
            $customer = Customer::create(['name' => 'Cancel User', 'email' => 'cancel@test.com', 'status' => 'active']);

            $subscription = Subscription::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
                'price_cents' => 50000,
                'billing_interval' => 'monthly',
                'status' => SubscriptionStatus::Active,
                'start_date' => '2025-01-01',
                'auto_renew' => true,
                'current_period_start' => '2025-01-01',
                'current_period_end' => '2025-01-31',
                'next_billing_at' => CarbonImmutable::parse('2025-01-01'),
            ]);
            $subscriptionId = $subscription->id;
        });

        $this->postJson("/api/v1/subscriptions/{$subscriptionId}/cancel", [
            'cancel_at_period_end' => true,
        ], $this->authHeaders())->assertOk();

        TenantContext::runAs($tenantId, function () use ($subscriptionId) {
            $subscription = Subscription::findOrFail($subscriptionId);
            $result = app(BillingCycleService::class)->billSubscription($subscription);

            $this->assertSame('billed', $result);

            $invoice = Invoice::where('subscription_id', $subscriptionId)->first();
            $this->assertNotNull($invoice);
            $this->assertSame(50000, $invoice->total_cents);
            $this->assertNotNull($invoice->journal_entry_id);

            $subscription->refresh();
            $this->assertSame(SubscriptionStatus::Cancelled, $subscription->status);
        });
    }

    public function test_plan_price_change_does_not_affect_existing_subscription_billing(): void
    {
        $data = $this->registerTenant();
        $tenantId = $data['tenant']['id'];
        $subscriptionId = null;
        $planId = null;

        TenantContext::runAs($tenantId, function () use ($tenantId, &$subscriptionId, &$planId) {
            $plan = SubscriptionPlan::create([
                'tenant_id' => $tenantId,
                'name' => 'Legacy',
                'price_cents' => 40000,
                'billing_interval' => 'monthly',
                'status' => 'active',
            ]);
            $planId = $plan->id;

            $customer = Customer::create(['name' => 'Price Lock', 'email' => 'price@test.com', 'status' => 'active']);
            $subscription = Subscription::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
                'price_cents' => 40000,
                'billing_interval' => 'monthly',
                'status' => SubscriptionStatus::Active,
                'start_date' => '2025-01-01',
                'auto_renew' => true,
                'current_period_start' => '2025-01-01',
                'current_period_end' => '2025-01-31',
                'next_billing_at' => CarbonImmutable::parse('2025-01-01'),
            ]);
            $subscriptionId = $subscription->id;

            SubscriptionPlan::where('id', $planId)->update(['price_cents' => 99000]);
        });

        TenantContext::runAs($tenantId, function () use ($subscriptionId) {
            $subscription = Subscription::findOrFail($subscriptionId);
            app(BillingCycleService::class)->billSubscription($subscription);

            $invoice = Invoice::where('subscription_id', $subscriptionId)->first();
            $this->assertSame(40000, $invoice->total_cents);
        });
    }

    public function test_billing_same_period_twice_creates_one_invoice(): void
    {
        $data = $this->registerTenant();
        $tenantId = $data['tenant']['id'];

        TenantContext::runAs($tenantId, function () use ($tenantId) {
            $plan = SubscriptionPlan::create([
                'tenant_id' => $tenantId,
                'name' => 'Idempotent',
                'price_cents' => 10000,
                'billing_interval' => 'monthly',
                'status' => 'active',
            ]);
            $customer = Customer::create(['name' => 'Idempotent', 'email' => 'idempotent@test.com', 'status' => 'active']);
            $subscription = Subscription::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
                'price_cents' => 10000,
                'billing_interval' => 'monthly',
                'status' => SubscriptionStatus::Active,
                'start_date' => '2025-01-01',
                'auto_renew' => true,
                'current_period_start' => '2025-01-01',
                'current_period_end' => '2025-01-31',
                'next_billing_at' => CarbonImmutable::parse('2025-01-01'),
            ]);

            $service = app(BillingCycleService::class);
            $this->assertSame('billed', $service->billSubscription($subscription->fresh()));

            // Simulate a worker retry for the same billing window (before a new period starts).
            $subscription->refresh()->update([
                'current_period_start' => '2025-01-01',
                'current_period_end' => '2025-01-31',
                'next_billing_at' => CarbonImmutable::parse('2025-01-01'),
            ]);

            $this->assertSame('skipped', $service->billSubscription($subscription->fresh()));
            $this->assertSame(1, Invoice::where('subscription_id', $subscription->id)->count());
        });
    }

    public function test_repeated_invoice_creation_for_same_period_creates_one_invoice(): void
    {
        $data = $this->registerTenant();
        $tenantId = $data['tenant']['id'];

        TenantContext::runAs($tenantId, function () use ($tenantId) {
            $plan = SubscriptionPlan::create([
                'tenant_id' => $tenantId,
                'name' => 'Concurrent',
                'price_cents' => 10000,
                'billing_interval' => 'monthly',
                'status' => 'active',
            ]);
            $customer = Customer::create(['name' => 'Concurrent', 'email' => 'concurrent@test.com', 'status' => 'active']);
            $subscription = Subscription::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
                'price_cents' => 10000,
                'billing_interval' => 'monthly',
                'status' => SubscriptionStatus::Active,
                'start_date' => '2025-01-01',
                'auto_renew' => true,
                'current_period_start' => '2025-01-01',
                'current_period_end' => '2025-01-31',
                'next_billing_at' => CarbonImmutable::parse('2025-01-01'),
            ]);

            $invoiceService = app(\App\Services\Billing\InvoiceService::class);
            $invoiceIds = [];

            for ($i = 0; $i < 5; $i++) {
                $invoiceIds[] = $invoiceService->createForSubscription($subscription->fresh())->id;
            }

            $this->assertSame(1, Invoice::where('subscription_id', $subscription->id)->count());
            $this->assertSame(1, collect($invoiceIds)->unique()->count());
        });
    }
}
