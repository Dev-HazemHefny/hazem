<?php

namespace App\Actions;

use App\Services\Billing\BillingCycleService;
use Carbon\CarbonImmutable;

class RunBillingCycleAction
{
    public function __construct(
        private readonly BillingCycleService $billingCycleService,
    ) {}

    public function execute(?CarbonImmutable $asOf = null): array
    {
        return $this->billingCycleService->runBillingCycle($asOf);
    }
}
