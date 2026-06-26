<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class ProrationCalculator
{
    /**
     * Calculate mid-cycle plan change amounts using day-based proration.
     *
     * @return array{
     *     total_days: int,
     *     days_used: int,
     *     days_remaining: int,
     *     old_used_amount_cents: int,
     *     old_unused_credit_cents: int,
     *     new_remaining_charge_cents: int,
     *     net_amount_cents: int,
     * }
     */
    public function calculate(
        int $oldPriceCents,
        int $newPriceCents,
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd,
        CarbonImmutable $effectiveDate,
    ): array {
        $periodStart = $periodStart->startOfDay();
        $periodEnd = $periodEnd->startOfDay();
        $effectiveDate = $effectiveDate->startOfDay();

        $totalDays = (int) $periodStart->diffInDays($periodEnd) + 1;
        $daysRemaining = max(0, min($totalDays, (int) $effectiveDate->diffInDays($periodEnd) + 1));
        $daysUsed = max(0, $totalDays - $daysRemaining);

        $oldUsedAmount = $this->prorateAmount($oldPriceCents, $daysUsed, $totalDays);
        $oldUnusedCredit = $oldPriceCents - $oldUsedAmount;
        $newRemainingCharge = $this->prorateAmount($newPriceCents, $daysRemaining, $totalDays);
        $netAmount = $newRemainingCharge - $oldUnusedCredit;

        return [
            'total_days' => $totalDays,
            'days_used' => $daysUsed,
            'days_remaining' => $daysRemaining,
            'old_used_amount_cents' => $oldUsedAmount,
            'old_unused_credit_cents' => $oldUnusedCredit,
            'new_remaining_charge_cents' => $newRemainingCharge,
            'net_amount_cents' => $netAmount,
        ];
    }

    private function prorateAmount(int $amountCents, int $days, int $totalDays): int
    {
        if ($totalDays <= 0 || $days <= 0) {
            return 0;
        }

        return (int) round($amountCents * $days / $totalDays);
    }
}
