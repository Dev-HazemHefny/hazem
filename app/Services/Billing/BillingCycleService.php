<?php

namespace App\Services\Billing;

use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Services\Tenancy\TenantTimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BillingCycleService
{
    public function __construct(
        private readonly TenantTimezoneService $timezoneService,
        private readonly InvoiceService $invoiceService,
    ) {}

    /**
     * Run billing cycle for all due active subscriptions.
     *
     * @return array{billed: int, skipped: int, failed: int}
     */
    public function runBillingCycle(?CarbonImmutable $asOf = null): array
    {
        $asOf ??= CarbonImmutable::now('UTC');
        $stats = ['billed' => 0, 'skipped' => 0, 'failed' => 0];

        $dueSubscriptions = Subscription::where('status', SubscriptionStatus::Active)
            ->where('next_billing_at', '<=', $asOf)
            ->where(function ($query) {
                $query->where('cancel_at_period_end', false)
                    ->orWhereNull('cancel_at_period_end');
            })
            ->get();

        foreach ($dueSubscriptions as $subscription) {
            try {
                $result = $this->billSubscription($subscription);
                $stats[$result]++;
            } catch (\Throwable $e) {
                $stats['failed']++;
                Log::error('Billing failed for subscription', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * Bill a single subscription with idempotency and period advance only on success.
     */
    public function billSubscription(Subscription $subscription): string
    {
        try {
            return DB::transaction(function () use ($subscription) {
                $subscription = Subscription::where('id', $subscription->id)->lockForUpdate()->firstOrFail();

                if ($subscription->status !== SubscriptionStatus::Active) {
                    return 'skipped';
                }

                if ($subscription->cancel_at_period_end) {
                    $subscription->update([
                        'status' => SubscriptionStatus::Cancelled,
                        'auto_renew' => false,
                    ]);

                    return 'skipped';
                }

                $idempotencyKey = sprintf(
                    '%s:%s:%s',
                    $subscription->id,
                    $subscription->current_period_start->toDateString(),
                    $subscription->current_period_end->toDateString(),
                );

                if (Invoice::where('billing_idempotency_key', $idempotencyKey)->exists()) {
                    return 'skipped';
                }

                $invoice = $this->invoiceService->createForSubscription($subscription);
                $this->invoiceService->postInvoiceJournalEntry($invoice);
                $this->invoiceService->createRecognitionSchedules($invoice);

                $subscription->load('plan');
                $nextPeriod = $this->timezoneService->advanceBillingPeriod(
                    CarbonImmutable::parse($subscription->current_period_end->toDateString()),
                    $subscription->plan->billing_interval->value,
                );

                $subscription->update([
                    'current_period_start' => $nextPeriod['period_start']->toDateString(),
                    'current_period_end' => $nextPeriod['period_end']->toDateString(),
                    'next_billing_at' => $nextPeriod['next_billing_at'],
                ]);

                if ($subscription->end_date && $nextPeriod['period_start']->gt($subscription->end_date)) {
                    $subscription->update([
                        'status' => SubscriptionStatus::Expired,
                        'auto_renew' => false,
                    ]);
                }

                return 'billed';
            });
        } catch (UniqueConstraintViolationException) {
            return 'skipped';
        }
    }
}
