<?php

namespace Tests\Feature\Jobs;

use App\Actions\RunBillingCycleAction;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Tests\Concerns\InteractsWithTenantApi;
use Tests\Concerns\RefreshDatabaseForTesting;
use Tests\TestCase;

class JobEndpointTest extends TestCase
{
    use InteractsWithTenantApi, RefreshDatabaseForTesting;

    public function test_revenue_recognition_job_accepts_period_end_parameter(): void
    {
        $this->registerTenant();

        $this->postJson('/api/v1/jobs/run-revenue-recognition', [
            'period_end' => '2025-01-31',
        ], [
            'X-Cron-Secret' => config('services.cron.secret'),
        ])->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_billing_cycle_runs_synchronously_in_tests(): void
    {
        $data = $this->registerTenant();
        $tenantId = $data['tenant']['id'];

        TenantContext::runAs($tenantId, function () use ($tenantId) {
            $plan = SubscriptionPlan::create([
                'tenant_id' => $tenantId,
                'name' => 'Job Plan',
                'price_cents' => 15000,
                'billing_interval' => 'monthly',
                'status' => 'active',
            ]);
            $customer = Customer::create(['name' => 'Job Customer', 'email' => 'job@test.com', 'status' => 'active']);
            Subscription::create([
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

            $stats = app(RunBillingCycleAction::class)->execute(CarbonImmutable::parse('2025-01-01'));
            $this->assertSame(1, $stats['billed']);
            $this->assertSame(1, Invoice::count());
        });
    }
}
