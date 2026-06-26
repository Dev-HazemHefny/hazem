<?php

namespace App\Services\Accounting;

use App\DTOs\JournalEntryDraft;
use App\Enums\RecognitionScheduleStatus;
use App\Enums\SubscriptionStatus;
use App\Models\RevenueRecognitionSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RevenueRecognitionService
{
    public function __construct(
        private readonly JournalEntryService $journalEntryService,
        private readonly ChartOfAccountsService $chartOfAccountsService,
    ) {}

    /**
     * Recognize revenue for all pending schedules whose period_end <= run date.
     *
     * @return array{recognized: int, skipped: int, failed: int}
     */
    public function recognizeDueSchedules(?CarbonImmutable $runDate = null): array
    {
        $runDate ??= CarbonImmutable::now('UTC');
        $stats = ['recognized' => 0, 'skipped' => 0, 'failed' => 0];

        $schedules = RevenueRecognitionSchedule::where('status', RecognitionScheduleStatus::Pending)
            ->whereDate('period_end', '<=', $runDate->toDateString())
            ->orderBy('period_end')
            ->get();

        foreach ($schedules as $schedule) {
            try {
                $result = $this->recognizeSchedule($schedule);
                $stats[$result]++;
            } catch (\Throwable $e) {
                $stats['failed']++;
                Log::error('Revenue recognition failed', [
                    'schedule_id' => $schedule->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * Cancel pending recognition schedules for a subscription from an effective date forward.
     */
    public function cancelPendingSchedulesForSubscription(
        string $subscriptionId,
        CarbonImmutable $effectiveDate,
        bool $cancelAllPending = false,
    ): int {
        $query = RevenueRecognitionSchedule::query()
            ->where('status', RecognitionScheduleStatus::Pending)
            ->whereHas('invoice', fn ($q) => $q->where('subscription_id', $subscriptionId));

        if (! $cancelAllPending) {
            $query->where('period_start', '>=', $effectiveDate->toDateString());
        }

        return $query->update(['status' => RecognitionScheduleStatus::Cancelled]);
    }

    /**
     * Recognize a single schedule with row lock and idempotency.
     */
    public function recognizeSchedule(RevenueRecognitionSchedule $schedule): string
    {
        try {
            return DB::transaction(function () use ($schedule) {
                $schedule = RevenueRecognitionSchedule::where('id', $schedule->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($schedule->status !== RecognitionScheduleStatus::Pending) {
                    return 'skipped';
                }

                $schedule->load('invoice.subscription');
                $subscription = $schedule->invoice?->subscription;

                if ($subscription && $subscription->status === SubscriptionStatus::Cancelled) {
                    if ($subscription->cancelled_at
                        && $schedule->period_start->gt($subscription->cancelled_at->startOfDay())) {
                        $schedule->update(['status' => RecognitionScheduleStatus::Cancelled]);

                        return 'skipped';
                    }
                }

                $tenantId = $schedule->tenant_id;
                $deferredAccount = $this->chartOfAccountsService->findByCode($tenantId, '2100');
                $revenueAccount = $this->chartOfAccountsService->findByCode($tenantId, '4000');

                $idempotencyKey = $schedule->recognition_idempotency_key
                    ?? sprintf('%s:%s', $schedule->id, $schedule->period_end->toDateString());

                $draft = new JournalEntryDraft(
                    tenantId: $tenantId,
                    entryDate: $schedule->period_end->toDateString(),
                    description: "Revenue recognition for period ending {$schedule->period_end->toDateString()}",
                    lines: [
                        [
                            'account_id' => $deferredAccount->id,
                            'debit_cents' => $schedule->amount_cents,
                            'credit_cents' => 0,
                            'description' => 'Deferred Revenue',
                        ],
                        [
                            'account_id' => $revenueAccount->id,
                            'debit_cents' => 0,
                            'credit_cents' => $schedule->amount_cents,
                            'description' => 'Subscription Revenue',
                        ],
                    ],
                    idempotencyKey: $idempotencyKey,
                );

                $entry = $this->journalEntryService->post($draft);

                $schedule->update([
                    'status' => RecognitionScheduleStatus::Recognized,
                    'journal_entry_id' => $entry->id,
                    'recognition_idempotency_key' => $idempotencyKey,
                ]);

                return 'recognized';
            });
        } catch (UniqueConstraintViolationException) {
            return 'skipped';
        }
    }

    public function createSchedulesForInvoice(\App\Models\Invoice $invoice): array
    {
        return app(\App\Services\Billing\InvoiceService::class)->createRecognitionSchedules($invoice);
    }
}
