<?php

namespace App\Services\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\PlanStatus;
use App\Enums\SubscriptionStatus;
use App\Exceptions\InvalidSubscriptionStatusException;
use App\Exceptions\PlanInactiveException;
use App\Exceptions\SubscriptionHasOpenInvoicesException;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\Accounting\RevenueRecognitionService;
use App\Services\Tenancy\TenantTimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public function __construct(
        private readonly TenantTimezoneService $timezoneService,
        private readonly RevenueRecognitionService $revenueRecognitionService,
    ) {}

    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Subscription::with(['customer', 'plan']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function create(array $data): Subscription
    {
        $plan = SubscriptionPlan::findOrFail($data['plan_id']);

        if ($plan->status !== PlanStatus::Active) {
            throw new PlanInactiveException();
        }

        $startDate = $this->timezoneService->parseDate($data['start_date']);
        $period = $this->timezoneService->calculateBillingPeriod(
            $startDate,
            $plan->billing_interval->value,
        );

        return Subscription::create([
            'customer_id' => $data['customer_id'],
            'plan_id' => $plan->id,
            'price_cents' => $plan->price_cents,
            'billing_interval' => $plan->billing_interval->value,
            'status' => SubscriptionStatus::Active,
            'start_date' => $startDate->toDateString(),
            'end_date' => $data['end_date'] ?? null,
            'auto_renew' => $data['auto_renew'] ?? true,
            'current_period_start' => $period['period_start']->toDateString(),
            'current_period_end' => $period['period_end']->toDateString(),
            'next_billing_at' => $period['next_billing_at'],
            'cancel_at_period_end' => false,
        ]);
    }

    public function find(string $id): Subscription
    {
        return Subscription::with(['customer', 'plan'])->findOrFail($id);
    }

    public function update(Subscription $subscription, array $data): Subscription
    {
        $subscription->update(array_filter([
            'end_date' => $data['end_date'] ?? null,
            'auto_renew' => $data['auto_renew'] ?? null,
        ], fn ($v) => $v !== null));

        return $subscription->fresh(['customer', 'plan']);
    }

    public function cancel(string $subscriptionId, bool $cancelAtPeriodEnd = true): Subscription
    {
        return DB::transaction(function () use ($subscriptionId, $cancelAtPeriodEnd) {
            $subscription = Subscription::where('id', $subscriptionId)->lockForUpdate()->firstOrFail();

            if (! in_array($subscription->status, [SubscriptionStatus::Active, SubscriptionStatus::PastDue], true)) {
                throw new InvalidSubscriptionStatusException();
            }

            $now = now();

            if ($cancelAtPeriodEnd) {
                $serviceEnd = $subscription->end_date
                    && $subscription->end_date->lte($subscription->current_period_end)
                    ? $subscription->end_date
                    : $subscription->current_period_end;

                $subscription->update([
                    'cancel_at_period_end' => true,
                    'cancelled_at' => $now,
                    'end_date' => $serviceEnd,
                ]);
            } else {
                $subscription->update([
                    'status' => SubscriptionStatus::Cancelled,
                    'cancel_at_period_end' => false,
                    'cancelled_at' => $now,
                    'auto_renew' => false,
                ]);

                $this->revenueRecognitionService->cancelPendingSchedulesForSubscription(
                    $subscription->id,
                    CarbonImmutable::today(),
                    cancelAllPending: true,
                );
            }

            return $subscription->fresh(['customer', 'plan']);
        });
    }

    public function delete(string $subscriptionId): Subscription
    {
        return DB::transaction(function () use ($subscriptionId) {
            $subscription = Subscription::where('id', $subscriptionId)->lockForUpdate()->firstOrFail();

            $hasOpenInvoices = Invoice::where('subscription_id', $subscription->id)
                ->whereIn('status', [
                    InvoiceStatus::Open,
                    InvoiceStatus::PartiallyPaid,
                    InvoiceStatus::Overdue,
                ])
                ->exists();

            if ($hasOpenInvoices) {
                throw new SubscriptionHasOpenInvoicesException();
            }

            if (in_array($subscription->status, [SubscriptionStatus::Active, SubscriptionStatus::PastDue], true)) {
                $subscription->update([
                    'status' => SubscriptionStatus::Cancelled,
                    'cancel_at_period_end' => false,
                    'cancelled_at' => now(),
                    'auto_renew' => false,
                ]);
            }

            $subscription->delete();

            return $subscription;
        });
    }
}
