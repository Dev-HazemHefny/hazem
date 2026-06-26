<?php

namespace App\Services\Billing;

use App\Enums\PlanStatus;
use App\Enums\RecognitionScheduleStatus;
use App\Enums\SubscriptionStatus;
use App\Exceptions\InvalidPlanChangeDateException;
use App\Exceptions\InvalidSubscriptionStatusException;
use App\Exceptions\PlanInactiveException;
use App\Exceptions\PlanIntervalMismatchException;
use App\Exceptions\SamePlanException;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\RevenueRecognitionSchedule;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\Tenancy\TenantTimezoneService;
use App\Support\ProrationCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PlanChangeService
{
    public function __construct(
        private readonly TenantTimezoneService $timezoneService,
        private readonly ProrationCalculator $prorationCalculator,
        private readonly InvoiceService $invoiceService,
    ) {}

    /**
     * Change subscription plan mid-cycle with day-based proration.
     *
     * @param  array{plan_id: string, effective_date?: string|null}  $data
     * @return array{
     *     subscription: Subscription,
     *     proration: array,
     *     proration_invoice: Invoice|null,
     * }
     */
    public function changePlan(string $subscriptionId, array $data): array
    {
        return DB::transaction(function () use ($subscriptionId, $data) {
            $subscription = Subscription::where('id', $subscriptionId)
                ->lockForUpdate()
                ->with('plan')
                ->firstOrFail();

            if (! in_array($subscription->status, [SubscriptionStatus::Active, SubscriptionStatus::PastDue], true)) {
                throw new InvalidSubscriptionStatusException();
            }

            if ($subscription->cancel_at_period_end) {
                throw new InvalidSubscriptionStatusException(
                    message: 'Cannot change plan on a subscription scheduled for cancellation.',
                    errorCode: 'SUBSCRIPTION_PENDING_CANCELLATION',
                );
            }

            $newPlan = SubscriptionPlan::findOrFail($data['plan_id']);
            $oldPlan = $subscription->plan;

            if ($newPlan->id === $oldPlan->id) {
                throw new SamePlanException();
            }

            if ($newPlan->status !== PlanStatus::Active) {
                throw new PlanInactiveException();
            }

            if ($newPlan->billing_interval !== $oldPlan->billing_interval) {
                throw new PlanIntervalMismatchException();
            }

            $effectiveDate = isset($data['effective_date'])
                ? $this->timezoneService->parseDate($data['effective_date'])
                : $this->timezoneService->now()->startOfDay();

            $periodInvoice = $this->findInvoiceCoveringDate($subscription, $effectiveDate);

            if (! $periodInvoice) {
                $subscription->update(['plan_id' => $newPlan->id]);

                AuditLog::create([
                    'tenant_id' => $subscription->tenant_id,
                    'user_id' => auth()->id(),
                    'action' => 'subscription.plan_changed',
                    'entity_type' => Subscription::class,
                    'entity_id' => $subscription->id,
                    'payload' => [
                        'old_plan_id' => $oldPlan->id,
                        'new_plan_id' => $newPlan->id,
                        'effective_date' => $effectiveDate->toDateString(),
                        'net_amount_cents' => 0,
                        'proration_invoice_id' => null,
                    ],
                    'ip_address' => request()?->ip(),
                    'created_at' => now(),
                ]);

                return [
                    'subscription' => $subscription->fresh(['customer', 'plan']),
                    'proration' => [
                        'effective_date' => $effectiveDate->toDateString(),
                        'old_plan_id' => $oldPlan->id,
                        'new_plan_id' => $newPlan->id,
                        'net_amount_cents' => 0,
                    ],
                    'proration_invoice' => null,
                ];
            }

            $periodStart = CarbonImmutable::parse($periodInvoice->period_start->toDateString());
            $periodEnd = CarbonImmutable::parse($periodInvoice->period_end->toDateString());

            if ($effectiveDate->lt($periodStart) || $effectiveDate->gt($periodEnd)) {
                throw new InvalidPlanChangeDateException();
            }

            $proration = $this->prorationCalculator->calculate(
                $oldPlan->price_cents,
                $newPlan->price_cents,
                $periodStart,
                $periodEnd,
                $effectiveDate,
            );

            $proration['effective_date'] = $effectiveDate->toDateString();
            $proration['old_plan_id'] = $oldPlan->id;
            $proration['new_plan_id'] = $newPlan->id;

            $this->adjustRecognitionSchedules($periodInvoice, $proration, $effectiveDate, $periodEnd);

            $idempotencyKey = sprintf(
                'proration:%s:%s:%s:%s',
                $subscription->id,
                $oldPlan->id,
                $newPlan->id,
                $effectiveDate->toDateString(),
            );

            $prorationInvoice = $this->invoiceService->createProrationInvoice(
                $subscription,
                $oldPlan,
                $newPlan,
                $proration,
                $effectiveDate,
                $idempotencyKey,
            );

            if ($prorationInvoice && ! $prorationInvoice->journal_entry_id) {
                $this->invoiceService->postProrationJournalEntry($prorationInvoice);
            }

            $subscription->update(['plan_id' => $newPlan->id]);

            AuditLog::create([
                'tenant_id' => $subscription->tenant_id,
                'user_id' => auth()->id(),
                'action' => 'subscription.plan_changed',
                'entity_type' => Subscription::class,
                'entity_id' => $subscription->id,
                'payload' => [
                    'old_plan_id' => $oldPlan->id,
                    'new_plan_id' => $newPlan->id,
                    'effective_date' => $effectiveDate->toDateString(),
                    'net_amount_cents' => $proration['net_amount_cents'],
                    'proration_invoice_id' => $prorationInvoice?->id,
                ],
                'ip_address' => request()?->ip(),
                'created_at' => now(),
            ]);

            return [
                'subscription' => $subscription->fresh(['customer', 'plan']),
                'proration' => $proration,
                'proration_invoice' => $prorationInvoice?->fresh(['lineItems']),
            ];
        });
    }

    private function findInvoiceCoveringDate(Subscription $subscription, CarbonImmutable $date): ?Invoice
    {
        return Invoice::where('subscription_id', $subscription->id)
            ->whereDate('period_start', '<=', $date->toDateString())
            ->whereDate('period_end', '>=', $date->toDateString())
            ->orderByDesc('issued_at')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $proration
     */
    private function adjustRecognitionSchedules(
        Invoice $invoice,
        array $proration,
        CarbonImmutable $effectiveDate,
        CarbonImmutable $periodEnd,
    ): void {
        $pendingSchedules = RevenueRecognitionSchedule::where('invoice_id', $invoice->id)
            ->where('status', RecognitionScheduleStatus::Pending)
            ->orderBy('period_start')
            ->get();

        foreach ($pendingSchedules as $schedule) {
            if ($schedule->period_start->gte($effectiveDate) || $schedule->period_end->gte($effectiveDate)) {
                $schedule->update(['status' => RecognitionScheduleStatus::Cancelled]);
            }
        }

        $recognizedAmount = (int) RevenueRecognitionSchedule::where('invoice_id', $invoice->id)
            ->where('status', RecognitionScheduleStatus::Recognized)
            ->sum('amount_cents');

        $oldUsedStillNeeded = max(0, $proration['old_used_amount_cents'] - $recognizedAmount);

        if ($oldUsedStillNeeded > 0) {
            $usedPeriodEnd = $effectiveDate->subDay();

            RevenueRecognitionSchedule::create([
                'tenant_id' => $invoice->tenant_id,
                'invoice_id' => $invoice->id,
                'amount_cents' => $oldUsedStillNeeded,
                'period_start' => $invoice->period_start,
                'period_end' => $usedPeriodEnd->toDateString(),
                'status' => RecognitionScheduleStatus::Pending,
                'recognition_idempotency_key' => sprintf(
                    '%s:plan-change-used:%s',
                    $invoice->id,
                    $usedPeriodEnd->toDateString(),
                ),
            ]);
        }

        if ($proration['new_remaining_charge_cents'] > 0) {
            RevenueRecognitionSchedule::create([
                'tenant_id' => $invoice->tenant_id,
                'invoice_id' => $invoice->id,
                'amount_cents' => $proration['new_remaining_charge_cents'],
                'period_start' => $effectiveDate->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'status' => RecognitionScheduleStatus::Pending,
                'recognition_idempotency_key' => sprintf(
                    '%s:plan-change-new:%s:%s',
                    $invoice->id,
                    $effectiveDate->toDateString(),
                    $periodEnd->toDateString(),
                ),
            ]);
        }
    }
}
