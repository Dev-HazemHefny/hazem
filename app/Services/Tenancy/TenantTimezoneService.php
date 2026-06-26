<?php

namespace App\Services\Tenancy;

use App\Models\Tenant;
use Carbon\CarbonImmutable;

class TenantTimezoneService
{
    public function getTimezone(?Tenant $tenant = null): string
    {
        $tenant ??= $this->resolveTenant();

        return $tenant?->timezone() ?? 'UTC';
    }

    public function now(?Tenant $tenant = null): CarbonImmutable
    {
        return CarbonImmutable::now($this->getTimezone($tenant));
    }

    public function parseDate(string $date, ?Tenant $tenant = null): CarbonImmutable
    {
        return CarbonImmutable::parse($date, $this->getTimezone($tenant))->startOfDay();
    }

    public function toUtcTimestamp(CarbonImmutable $tenantLocalMoment): CarbonImmutable
    {
        return $tenantLocalMoment->utc();
    }

    /**
     * Calculate subscription billing period boundaries in tenant-local calendar dates.
     *
     * @return array{period_start: CarbonImmutable, period_end: CarbonImmutable, next_billing_at: CarbonImmutable}
     */
    public function calculateBillingPeriod(
        CarbonImmutable $startDate,
        string $billingInterval,
        ?Tenant $tenant = null,
    ): array {
        $tz = $this->getTimezone($tenant);
        $periodStart = $startDate->setTimezone($tz)->startOfDay();

        $periodEnd = match ($billingInterval) {
            'monthly' => $periodStart->addMonth()->subDay(),
            'yearly' => $periodStart->addYear()->subDay(),
            default => $periodStart->addMonth()->subDay(),
        };

        $nextBillingAt = $periodEnd->addDay()->startOfDay()->utc();

        return [
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'next_billing_at' => $nextBillingAt,
        ];
    }

    /**
     * Advance subscription to the next billing period after successful invoice creation.
     *
     * @return array{period_start: CarbonImmutable, period_end: CarbonImmutable, next_billing_at: CarbonImmutable}
     */
    public function advanceBillingPeriod(
        CarbonImmutable $currentPeriodEnd,
        string $billingInterval,
        ?Tenant $tenant = null,
    ): array {
        $nextStart = $currentPeriodEnd->addDay();

        return $this->calculateBillingPeriod($nextStart, $billingInterval, $tenant);
    }

    /**
     * Convert tenant-local date boundaries to UTC for timestamp comparisons in jobs.
     */
    public function tenantDateToUtcEndOfDay(string $date, ?Tenant $tenant = null): CarbonImmutable
    {
        return $this->parseDate($date, $tenant)->endOfDay()->utc();
    }

    public function tenantDateToUtcStartOfDay(string $date, ?Tenant $tenant = null): CarbonImmutable
    {
        return $this->parseDate($date, $tenant)->startOfDay()->utc();
    }

    /**
     * Check whether a tenant-local calendar date is on or before the run date.
     */
    public function isDateOnOrBefore(string $date, CarbonImmutable $runDate, ?Tenant $tenant = null): bool
    {
        return $this->parseDate($date, $tenant)->lte($runDate->setTimezone($this->getTimezone($tenant))->startOfDay());
    }

    /**
     * Default invoice due date: issued_at + 30 days in tenant timezone, stored as UTC.
     */
    public function calculateDueAt(CarbonImmutable $issuedAt, int $paymentTermsDays = 30): CarbonImmutable
    {
        return $issuedAt->addDays($paymentTermsDays);
    }

    private function resolveTenant(): ?Tenant
    {
        $tenantId = \App\Support\TenantContext::id();

        return $tenantId ? Tenant::find($tenantId) : null;
    }
}
