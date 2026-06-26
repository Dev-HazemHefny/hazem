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
     * Run billing cycle for all due billable subscriptions (active and past_due).
     *
     * @return array{billed: int, skipped: int, failed: int}
     */
    public function runBillingCycle(?CarbonImmutable $asOf = null): array
    {
        $asOf ??= CarbonImmutable::now('UTC');
        $stats = ['billed' => 0, 'skipped' => 0, 'failed' => 0];

        $dueSubscriptions = Subscription::whereIn('status', [
            SubscriptionStatus::Active,
            SubscriptionStatus::PastDue,
        ])
            ->where('next_billing_at', '<=', $asOf)
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

                if (! in_array($subscription->status, [SubscriptionStatus::Active, SubscriptionStatus::PastDue], true)) {
                    return 'skipped';
                }

                $periodStart = CarbonImmutable::parse($subscription->current_period_start->toDateString());
                $periodEnd = CarbonImmutable::parse($subscription->current_period_end->toDateString());

                if ($subscription->cancel_at_period_end && $subscription->end_date) {
                    $serviceEnd = CarbonImmutable::parse($subscription->end_date->toDateString());

                    if ($periodStart->gt($serviceEnd)) {
                        $this->finalizeCancellation($subscription);

                        return 'skipped';
                    }
                }

                $idempotencyKey = $this->billingIdempotencyKey($subscription);

                if (Invoice::where('billing_idempotency_key', $idempotencyKey)->lockForUpdate()->exists()) {
                    if ($subscription->cancel_at_period_end) {
                        $this->finalizeCancellation($subscription);
                    }

                    return 'skipped';
                }

                $invoice = $this->invoiceService->createForSubscription($subscription);
                $this->invoiceService->postInvoiceJournalEntry($invoice);
                $this->invoiceService->createRecognitionSchedules($invoice);

                if ($subscription->cancel_at_period_end) {
                    $this->finalizeCancellation($subscription);

                    return 'billed';
                }

                $subscription->load('plan');
                $nextPeriod = $this->timezoneService->advanceBillingPeriod(
                    $periodEnd,
                    $subscription->billing_interval ?? $subscription->plan->billing_interval->value,
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

    private function billingIdempotencyKey(Subscription $subscription): string
    {
        return sprintf(
            '%s:%s:%s',
            $subscription->id,
            $subscription->current_period_start->toDateString(),
            $subscription->current_period_end->toDateString(),
        );
    }

    private function finalizeCancellation(Subscription $subscription): void
    {
        $subscription->update([
            'status' => SubscriptionStatus::Cancelled,
            'auto_renew' => false,
            'cancel_at_period_end' => false,
        ]);
    }
}
