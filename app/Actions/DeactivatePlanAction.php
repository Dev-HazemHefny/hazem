<?php

namespace App\Actions;

use App\Models\SubscriptionPlan;
use App\Services\Billing\PlanService;

class DeactivatePlanAction
{
    public function __construct(
        private readonly PlanService $planService,
    ) {}

    public function execute(string $planId): SubscriptionPlan
    {
        return $this->planService->deactivate($planId);
    }
}
