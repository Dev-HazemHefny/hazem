<?php

namespace Tests\Feature\Accounting;

use App\Actions\MarkPastDueSubscriptionsAction;
use App\Actions\RecordPaymentAction;
use App\Actions\RunBillingCycleAction;
use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Tests\Concerns\InteractsWithTenantApi;
use Tests\Concerns\RefreshDatabaseForTesting;
use Tests\TestCase;

class MarkPastDueAfterGracePeriodTest extends TestCase
{
    use InteractsWithTenantApi, RefreshDatabaseForTesting;

    public function test_open_invoice_past_grace_marks_subscription_past_due_and_overdue_invoice(): void
    {
        $data = $this->registerTenant();
        $tenantId = $data['tenant']['id'];

        TenantContext::runAs($tenantId, function () {
            $plan = SubscriptionPlan::create([
                'name' => 'Gold',
                'price_cents' => 20000,
                'currency' => 'USD',
                'billing_interval' => 'monthly',
                'status' => 'active',
            ]);

            $customer = Customer::create(['name' => 'Bob', 'email' => 'bob-pastdue@test.com', 'status' => 'active']);

            Subscription::create([
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'start_date' => '2025-01-01',
                'auto_renew' => true,
                'current_period_start' => '2025-01-01',
                'current_period_end' => '2025-01-31',
                'next_billing_at' => CarbonImmutable::parse('2025-01-01'),
            ]);

            app(RunBillingCycleAction::class)->execute(CarbonImmutable::parse('2025-01-01'));

            $invoice = Invoice::firstOrFail();
            $subscription = Subscription::firstOrFail();
            $invoice->update(['due_at' => CarbonImmutable::parse('2024-12-01 00:00:00', 'UTC')]);

            $stats = app(MarkPastDueSubscriptionsAction::class)->execute();

            $this->assertSame(1, $stats['invoices_marked_overdue']);
            $this->assertSame(1, $stats['marked_past_due']);

            $invoice->refresh();
            $subscription->refresh();

            $this->assertSame(InvoiceStatus::Overdue, $invoice->status);
            $this->assertSame(SubscriptionStatus::PastDue, $subscription->status);

            app(RecordPaymentAction::class)->execute($invoice, [
                'amount_cents' => 20000,
                'client_idempotency_key' => 'past-due-pay-1',
            ]);

            $subscription->refresh();
            $this->assertSame(SubscriptionStatus::Active, $subscription->status);
            $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        });
    }
}
