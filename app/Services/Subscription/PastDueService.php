<?php

namespace App\Services\Subscription;

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Tenancy\TenantTimezoneService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PastDueService
{
    public function __construct(
        private readonly TenantTimezoneService $timezoneService,
    ) {}

    /**
     * Mark overdue invoices, active subscriptions as past_due when grace expires,
     * and reactivate past_due subscriptions when all invoices are settled.
     *
     * @return array{invoices_marked_overdue: int, marked_past_due: int, reactivated: int}
     */
    public function markPastDueSubscriptions(?CarbonImmutable $asOf = null): array
    {
        $tenant = Tenant::find(TenantContext::id());
        $graceDays = $tenant?->gracePeriodDays() ?? 7;
        $asOf ??= $this->timezoneService->now($tenant);
        $stats = ['invoices_marked_overdue' => 0, 'marked_past_due' => 0, 'reactivated' => 0];

        $stats['invoices_marked_overdue'] = $this->markOverdueInvoices($graceDays, $asOf);

        $activeSubscriptions = Subscription::where('status', SubscriptionStatus::Active)->get();

        foreach ($activeSubscriptions as $subscription) {
            if ($this->shouldMarkPastDue($subscription, $graceDays, $asOf)) {
                DB::transaction(function () use ($subscription) {
                    $locked = Subscription::where('id', $subscription->id)->lockForUpdate()->firstOrFail();
                    if ($locked->status === SubscriptionStatus::Active) {
                        $locked->update(['status' => SubscriptionStatus::PastDue]);
                    }
                });
                $stats['marked_past_due']++;
            }
        }

        $pastDueSubscriptions = Subscription::where('status', SubscriptionStatus::PastDue)->get();

        foreach ($pastDueSubscriptions as $subscription) {
            if ($this->shouldReactivate($subscription)) {
                DB::transaction(function () use ($subscription) {
                    $locked = Subscription::where('id', $subscription->id)->lockForUpdate()->firstOrFail();
                    if ($locked->status === SubscriptionStatus::PastDue && $this->shouldReactivate($locked)) {
                        $locked->update(['status' => SubscriptionStatus::Active]);
                    }
                });
                $stats['reactivated']++;
            }
        }

        return $stats;
    }

    private function markOverdueInvoices(int $graceDays, CarbonImmutable $asOf): int
    {
        $cutoff = $asOf->utc()->subDays($graceDays);
        $count = 0;

        $invoices = Invoice::whereIn('status', [InvoiceStatus::Open, InvoiceStatus::PartiallyPaid])
            ->where('due_at', '<', $cutoff)
            ->get();

        foreach ($invoices as $invoice) {
            $invoice->update(['status' => InvoiceStatus::Overdue]);
            $count++;
        }

        return $count;
    }

    private function shouldMarkPastDue(Subscription $subscription, int $graceDays, CarbonImmutable $asOf): bool
    {
        return Invoice::where('subscription_id', $subscription->id)
            ->whereIn('status', [
                InvoiceStatus::Open,
                InvoiceStatus::PartiallyPaid,
                InvoiceStatus::Overdue,
            ])
            ->where('due_at', '<', $asOf->utc()->subDays($graceDays))
            ->exists();
    }

    private function shouldReactivate(Subscription $subscription): bool
    {
        $hasOpenInvoices = Invoice::where('subscription_id', $subscription->id)
            ->whereIn('status', [
                InvoiceStatus::Open,
                InvoiceStatus::PartiallyPaid,
                InvoiceStatus::Overdue,
            ])
            ->exists();

        return ! $hasOpenInvoices;
    }
}
