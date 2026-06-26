<?php

namespace Tests\Feature\Accounting;

use App\Actions\RecognizeSubscriptionRevenueAction;
use App\Enums\SubscriptionStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\Billing\BillingCycleService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Tests\Concerns\InteractsWithTenantApi;
use Tests\Concerns\RefreshDatabaseForTesting;
use Tests\TestCase;

class CancelRecognitionTest extends TestCase
{
    use InteractsWithTenantApi, RefreshDatabaseForTesting;

    public function test_immediate_cancel_stops_future_revenue_recognition(): void
    {
        $data = $this->registerTenant();
        $tenantId = $data['tenant']['id'];
        $subscriptionId = null;

        TenantContext::runAs($tenantId, function () use (&$subscriptionId) {
            $plan = SubscriptionPlan::create([
                'name' => 'Monthly',
                'price_cents' => 50000,
                'billing_interval' => 'monthly',
                'status' => 'active',
            ]);
            $customer = Customer::create(['name' => 'Mid Cancel', 'email' => 'mid@test.com', 'status' => 'active']);
            $subscription = Subscription::create([
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

            app(BillingCycleService::class)->billSubscription($subscription);
        });

        $this->postJson("/api/v1/subscriptions/{$subscriptionId}/cancel", [
            'cancel_at_period_end' => false,
        ], $this->authHeaders())->assertOk();

        TenantContext::runAs($tenantId, function () {
            $revenueAccount = \App\Models\Account::where('code', '4000')->first();
            $revenueBefore = (int) JournalLine::where('account_id', $revenueAccount->id)->sum('credit_cents');

            app(RecognizeSubscriptionRevenueAction::class)->execute(CarbonImmutable::parse('2025-01-31'));

            $revenueAfter = (int) JournalLine::where('account_id', $revenueAccount->id)->sum('credit_cents');
            $this->assertSame($revenueBefore, $revenueAfter);
        });
    }
}
