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
     * Mark active subscriptions as past_due when open invoices exceed grace period.
     * Revert past_due subscriptions to active when all invoices are paid or void.
     *
     * @return array{marked_past_due: int, reactivated: int}
     */
    public function markPastDueSubscriptions(?CarbonImmutable $asOf = null): array
    {
        $tenant = Tenant::find(TenantContext::id());
        $graceDays = $tenant?->gracePeriodDays() ?? 7;
        $asOf ??= $this->timezoneService->now($tenant);
        $stats = ['marked_past_due' => 0, 'reactivated' => 0];

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

    private function shouldMarkPastDue(Subscription $subscription, int $graceDays, CarbonImmutable $asOf): bool
    {
        return Invoice::where('subscription_id', $subscription->id)
            ->whereIn('status', [InvoiceStatus::Open, InvoiceStatus::PartiallyPaid])
            ->where('due_at', '<', $asOf->utc()->subDays($graceDays))
            ->exists();
    }

    private function shouldReactivate(Subscription $subscription): bool
    {
        $hasOpenInvoices = Invoice::where('subscription_id', $subscription->id)
            ->whereIn('status', [InvoiceStatus::Open, InvoiceStatus::PartiallyPaid])
            ->exists();

        return ! $hasOpenInvoices;
    }
}
