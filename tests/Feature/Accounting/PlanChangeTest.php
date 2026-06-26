<?php

namespace Tests\Feature\Accounting;

use App\Enums\SubscriptionStatus;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\JournalLine;
use App\Models\RevenueRecognitionSchedule;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\Billing\BillingCycleService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Tests\Concerns\RefreshDatabaseForTesting;
use Tests\Concerns\InteractsWithTenantApi;
use Tests\TestCase;

class PlanChangeTest extends TestCase
{
    use InteractsWithTenantApi, RefreshDatabaseForTesting;

    public function test_plan_change_before_billing_updates_plan_only(): void
    {
        $data = $this->registerTenant();
        $tenantId = $data['tenant']['id'];
        $subscriptionId = null;
        $premiumPlanId = null;

        TenantContext::runAs($tenantId, function () use (&$subscriptionId, &$premiumPlanId, $tenantId) {
            $basic = SubscriptionPlan::create([
                'tenant_id' => $tenantId,
                'name' => 'Basic',
                'price_cents' => 10000,
                'billing_interval' => 'monthly',
                'status' => 'active',
            ]);
            $premium = SubscriptionPlan::create([
                'tenant_id' => $tenantId,
                'name' => 'Premium',
                'price_cents' => 20000,
                'billing_interval' => 'monthly',
                'status' => 'active',
            ]);
            $premiumPlanId = $premium->id;

            $customer = Customer::create(['name' => 'Plan Change', 'email' => 'plan@test.com', 'status' => 'active']);
            $subscription = Subscription::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'plan_id' => $basic->id,
                'status' => SubscriptionStatus::Active,
                'start_date' => '2025-01-01',
                'auto_renew' => true,
                'current_period_start' => '2025-01-01',
                'current_period_end' => '2025-01-31',
                'next_billing_at' => CarbonImmutable::parse('2025-02-01'),
            ]);
            $subscriptionId = $subscription->id;
        });

        $this->postJson("/api/v1/subscriptions/{$subscriptionId}/change-plan", [
            'plan_id' => $premiumPlanId,
        ], $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('data.subscription.plan_id', $premiumPlanId)
            ->assertJsonPath('data.proration_invoice', null);

        $this->assertSame(0, Invoice::count());
    }

    public function test_mid_cycle_upgrade_creates_proration_invoice_and_balanced_journal(): void
    {
        $data = $this->registerTenant();
        $tenantId = $data['tenant']['id'];
        $subscriptionId = null;
        $premiumPlanId = null;

        TenantContext::runAs($tenantId, function () use (&$subscriptionId, &$premiumPlanId, $tenantId) {
            $basic = SubscriptionPlan::create([
                'tenant_id' => $tenantId,
                'name' => 'Basic',
                'price_cents' => 50000,
                'billing_interval' => 'monthly',
                'status' => 'active',
            ]);
            $premium = SubscriptionPlan::create([
                'tenant_id' => $tenantId,
                'name' => 'Premium',
                'price_cents' => 80000,
                'billing_interval' => 'monthly',
                'status' => 'active',
            ]);
            $premiumPlanId = $premium->id;

            $customer = Customer::create(['name' => 'Upgrade', 'email' => 'upgrade@test.com', 'status' => 'active']);
            $subscription = Subscription::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'plan_id' => $basic->id,
                'status' => SubscriptionStatus::Active,
                'start_date' => '2025-01-01',
                'auto_renew' => true,
                'current_period_start' => '2025-01-01',
                'current_period_end' => '2025-01-31',
                'next_billing_at' => CarbonImmutable::parse('2025-01-01'),
            ]);
            $subscriptionId = $subscription->id;

            app(BillingCycleService::class)->billSubscription($subscription);
        });

        $response = $this->postJson("/api/v1/subscriptions/{$subscriptionId}/change-plan", [
            'plan_id' => $premiumPlanId,
            'effective_date' => '2025-01-16',
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('data.subscription.plan_id', $premiumPlanId)
            ->assertJsonPath('data.proration.net_amount_cents', 15484)
            ->assertJsonPath('data.proration_invoice.total_cents', 15484);

        TenantContext::runAs($tenantId, function () {
            $this->assertSame(2, Invoice::count());

            $prorationInvoice = Invoice::where('billing_idempotency_key', 'like', 'proration:%')->first();
            $this->assertNotNull($prorationInvoice);
            $this->assertSame(15484, $prorationInvoice->total_cents);
            $this->assertNotNull($prorationInvoice->journal_entry_id);

            $debits = (int) JournalLine::where('journal_entry_id', $prorationInvoice->journal_entry_id)->sum('debit_cents');
            $credits = (int) JournalLine::where('journal_entry_id', $prorationInvoice->journal_entry_id)->sum('credit_cents');
            $this->assertSame($debits, $credits);

            $periodInvoice = Invoice::orderBy('created_at')->first();
            $this->assertSame(2, RevenueRecognitionSchedule::where('invoice_id', $periodInvoice->id)->where('status', 'pending')->count());

            $ar = Account::where('code', '1100')->first();
            $this->assertSame(65484, (int) JournalLine::where('account_id', $ar->id)->sum('debit_cents'));
        });
    }

    public function test_mid_cycle_downgrade_creates_credit_memo_without_payment(): void
    {
        $data = $this->registerTenant();
        $tenantId = $data['tenant']['id'];
        $subscriptionId = null;
        $basicPlanId = null;

        TenantContext::runAs($tenantId, function () use (&$subscriptionId, &$basicPlanId, $tenantId) {
            $premium = SubscriptionPlan::create([
                'tenant_id' => $tenantId,
                'name' => 'Premium',
                'price_cents' => 30000,
                'billing_interval' => 'monthly',
                'status' => 'active',
            ]);
            $basic = SubscriptionPlan::create([
                'tenant_id' => $tenantId,
                'name' => 'Basic',
                'price_cents' => 10000,
                'billing_interval' => 'monthly',
                'status' => 'active',
            ]);
            $basicPlanId = $basic->id;

            $customer = Customer::create(['name' => 'Downgrade', 'email' => 'downgrade@test.com', 'status' => 'active']);
            $subscription = Subscription::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'plan_id' => $premium->id,
                'price_cents' => 30000,
                'billing_interval' => 'monthly',
                'status' => SubscriptionStatus::Active,
                'start_date' => '2025-01-01',
                'auto_renew' => true,
                'current_period_start' => '2025-01-01',
                'current_period_end' => '2025-01-31',
                'next_billing_at' => CarbonImmutable::parse('2025-01-01'),
            ]);
            $subscriptionId = $subscription->id;

            app(BillingCycleService::class)->billSubscription($subscription);
        });

        $this->postJson("/api/v1/subscriptions/{$subscriptionId}/change-plan", [
            'plan_id' => $basicPlanId,
            'effective_date' => '2025-01-16',
        ], $this->authHeaders())->assertOk();

        TenantContext::runAs($tenantId, function () {
            $creditMemo = Invoice::where('status', 'credit_memo')->first();
            $this->assertNotNull($creditMemo);
            $this->assertLessThan(0, $creditMemo->total_cents);
            $this->assertSame(0, $creditMemo->amount_due_cents);
            $this->assertNotNull($creditMemo->journal_entry_id);
        });
    }

    public function test_change_plan_rejects_same_plan(): void
    {
        $data = $this->registerTenant();
        $tenantId = $data['tenant']['id'];
        $subscriptionId = null;
        $planId = null;

        TenantContext::runAs($tenantId, function () use (&$subscriptionId, &$planId, $tenantId) {
            $plan = SubscriptionPlan::create([
                'tenant_id' => $tenantId,
                'name' => 'Solo',
                'price_cents' => 10000,
                'billing_interval' => 'monthly',
                'status' => 'active',
            ]);
            $planId = $plan->id;

            $customer = Customer::create(['name' => 'Same', 'email' => 'same@test.com', 'status' => 'active']);
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

    public function test_change_plan_rejects_different_billing_interval(): void
    {
        $data = $this->registerTenant();
        $tenantId = $data['tenant']['id'];
        $subscriptionId = null;
        $yearlyPlanId = null;

        TenantContext::runAs($tenantId, function () use (&$subscriptionId, &$yearlyPlanId, $tenantId) {
            $monthly = SubscriptionPlan::create([
                'tenant_id' => $tenantId,
                'name' => 'Monthly',
                'price_cents' => 10000,
                'billing_interval' => 'monthly',
                'status' => 'active',
            ]);
            $yearly = SubscriptionPlan::create([
                'tenant_id' => $tenantId,
                'name' => 'Yearly',
                'price_cents' => 100000,
                'billing_interval' => 'yearly',
                'status' => 'active',
            ]);
            $yearlyPlanId = $yearly->id;

            $customer = Customer::create(['name' => 'Interval', 'email' => 'interval@test.com', 'status' => 'active']);
            $subscription = Subscription::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'plan_id' => $monthly->id,
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
            'plan_id' => $yearlyPlanId,
        ], $this->authHeaders())
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PLAN_INTERVAL_MISMATCH');
    }
}
