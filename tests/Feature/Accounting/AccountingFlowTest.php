<?php

namespace Tests\Feature\Accounting;

use App\Actions\RecognizeSubscriptionRevenueAction;
use App\Actions\RecordPaymentAction;
use App\Actions\RunBillingCycleAction;
use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\JournalLine;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\Billing\BillingCycleService;
use App\Services\Billing\InvoiceService;
use App\Services\Reporting\FinancialReportService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Tests\Concerns\RefreshDatabaseForTesting;
use Tests\Concerns\InteractsWithTenantApi;
use Tests\TestCase;

class AccountingFlowTest extends TestCase
{
    use InteractsWithTenantApi, RefreshDatabaseForTesting;

    public function test_billing_creates_balanced_journal_and_line_items(): void
    {
        $data = $this->registerTenant();
        $tenantId = $data['tenant']['id'];

        TenantContext::runAs($tenantId, function () use ($tenantId) {
            $plan = SubscriptionPlan::create([
                'name' => 'Pro',
                'price_cents' => 50000,
                'currency' => 'USD',
                'billing_interval' => 'monthly',
                'status' => 'active',
            ]);

            $customer = Customer::create([
                'name' => 'Alice',
                'email' => 'alice@test.com',
                'status' => 'active',
            ]);

            $subscription = Subscription::create([
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'start_date' => '2025-01-01',
                'auto_renew' => true,
                'current_period_start' => '2025-01-01',
                'current_period_end' => '2025-01-31',
                'next_billing_at' => CarbonImmutable::parse('2025-01-01'),
            ]);

            app(BillingCycleService::class)->billSubscription($subscription);

            $invoice = Invoice::first();
            $this->assertNotNull($invoice);
            $this->assertCount(1, $invoice->lineItems);
            $this->assertSame(50000, $invoice->lineItems->first()->amount_cents);
            $this->assertNotNull($invoice->journal_entry_id);

            $debits = (int) JournalLine::where('journal_entry_id', $invoice->journal_entry_id)->sum('debit_cents');
            $credits = (int) JournalLine::where('journal_entry_id', $invoice->journal_entry_id)->sum('credit_cents');
            $this->assertSame($debits, $credits);

            $ar = Account::where('code', '1100')->first();
            $deferred = Account::where('code', '2100')->first();
            $this->assertSame(50000, (int) JournalLine::where('account_id', $ar->id)->sum('debit_cents'));
            $this->assertSame(50000, (int) JournalLine::where('account_id', $deferred->id)->sum('credit_cents'));
        });
    }

    public function test_payment_reduces_ar_and_increases_cash(): void
    {
        $data = $this->registerTenant();
        $tenantId = $data['tenant']['id'];

        TenantContext::runAs($tenantId, function () {
            $this->seedSubscriptionAndBill(50000);

            $invoice = Invoice::first();
            app(RecordPaymentAction::class)->execute($invoice, [
                'amount_cents' => 50000,
                'client_idempotency_key' => 'pay-full-1',
            ]);

            $invoice->refresh();
            $this->assertSame(InvoiceStatus::Paid, $invoice->status);
            $this->assertSame(0, $invoice->amount_due_cents);

            $cash = Account::where('code', '1000')->first();
            $ar = Account::where('code', '1100')->first();
            $this->assertSame(50000, (int) JournalLine::where('account_id', $cash->id)->sum('debit_cents'));
            $this->assertSame(50000, (int) JournalLine::where('account_id', $ar->id)->sum('credit_cents'));
        });
    }

    public function test_unpaid_invoice_still_recognizes_revenue(): void
    {
        $data = $this->registerTenant();
        $tenantId = $data['tenant']['id'];

        TenantContext::runAs($tenantId, function () {
            $this->seedSubscriptionAndBill(50000);

            app(RecognizeSubscriptionRevenueAction::class)->execute(CarbonImmutable::parse('2025-01-31'));

            $revenue = Account::where('code', '4000')->first();
            $deferred = Account::where('code', '2100')->first();

            $this->assertSame(50000, (int) JournalLine::where('account_id', $revenue->id)->sum('credit_cents'));
            $this->assertSame(50000, (int) JournalLine::where('account_id', $deferred->id)->sum('debit_cents'));

            $invoice = Invoice::first();
            $this->assertSame(InvoiceStatus::Open, $invoice->status);
        });
    }

    public function test_yearly_plan_creates_twelve_recognition_schedules(): void
    {
        $data = $this->registerTenant();
        $tenantId = $data['tenant']['id'];

        TenantContext::runAs($tenantId, function () {
            $plan = SubscriptionPlan::create([
                'name' => 'Enterprise',
                'price_cents' => 120000,
                'currency' => 'USD',
                'billing_interval' => 'yearly',
                'status' => 'active',
            ]);

            $customer = Customer::create(['name' => 'Dave', 'email' => 'dave@test.com', 'status' => 'active']);

            $subscription = Subscription::create([
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'start_date' => '2025-01-01',
                'auto_renew' => true,
                'current_period_start' => '2025-01-01',
                'current_period_end' => '2025-12-31',
                'next_billing_at' => CarbonImmutable::parse('2025-01-01'),
            ]);

            $invoice = app(InvoiceService::class)->createForSubscription($subscription);
            app(InvoiceService::class)->postInvoiceJournalEntry($invoice);
            $schedules = app(InvoiceService::class)->createRecognitionSchedules($invoice);

            $this->assertCount(12, $schedules);
            $this->assertSame(120000, collect($schedules)->sum('amount_cents'));
        });
    }

    public function test_balance_sheet_is_balanced_after_full_cycle(): void
    {
        $data = $this->registerTenant();
        $tenantId = $data['tenant']['id'];

        TenantContext::runAs($tenantId, function () {
            $this->seedSubscriptionAndBill(50000);

            $invoice = Invoice::first();
            app(RecordPaymentAction::class)->execute($invoice, [
                'amount_cents' => 50000,
                'client_idempotency_key' => 'pay-bs-1',
            ]);

            app(RecognizeSubscriptionRevenueAction::class)->execute(CarbonImmutable::parse('2025-01-31'));

            $report = app(FinancialReportService::class)->balanceSheet('2025-01-31');
            $this->assertTrue($report['balanced']);
        });
    }

    private function seedSubscriptionAndBill(int $priceCents): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Gold',
            'price_cents' => $priceCents,
            'currency' => 'USD',
            'billing_interval' => 'monthly',
            'status' => 'active',
        ]);

        $customer = Customer::create(['name' => 'Bob', 'email' => 'bob@test.com', 'status' => 'active']);

        $subscription = Subscription::create([
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
    }
}
