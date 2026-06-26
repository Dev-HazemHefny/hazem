<?php

namespace Tests\Unit;

use App\Support\ProrationCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ProrationCalculatorTest extends TestCase
{
    public function test_mid_month_upgrade_proration(): void
    {
        $result = (new ProrationCalculator())->calculate(
            oldPriceCents: 50000,
            newPriceCents: 80000,
            periodStart: CarbonImmutable::parse('2025-01-01'),
            periodEnd: CarbonImmutable::parse('2025-01-31'),
            effectiveDate: CarbonImmutable::parse('2025-01-16'),
        );

        $this->assertSame(31, $result['total_days']);
        $this->assertSame(15, $result['days_used']);
        $this->assertSame(16, $result['days_remaining']);
        $this->assertSame(24194, $result['old_used_amount_cents']);
        $this->assertSame(25806, $result['old_unused_credit_cents']);
        $this->assertSame(41290, $result['new_remaining_charge_cents']);
        $this->assertSame(15484, $result['net_amount_cents']);
    }

    public function test_period_start_change_charges_full_difference(): void
    {
        $result = (new ProrationCalculator())->calculate(
            oldPriceCents: 50000,
            newPriceCents: 80000,
            periodStart: CarbonImmutable::parse('2025-01-01'),
            periodEnd: CarbonImmutable::parse('2025-01-31'),
            effectiveDate: CarbonImmutable::parse('2025-01-01'),
        );

        $this->assertSame(0, $result['days_used']);
        $this->assertSame(31, $result['days_remaining']);
        $this->assertSame(30000, $result['net_amount_cents']);
    }
}
