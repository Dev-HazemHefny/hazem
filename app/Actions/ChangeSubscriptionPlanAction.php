<?php

namespace App\Actions;

use App\Services\Billing\PlanChangeService;

class ChangeSubscriptionPlanAction
{
    public function __construct(
        private readonly PlanChangeService $planChangeService,
    ) {}

    /**
     * @param  array{plan_id: string, effective_date?: string|null}  $data
     * @return array{
     *     subscription: \App\Models\Subscription,
     *     proration: array,
     *     proration_invoice: \App\Models\Invoice|null,
     * }
     */
    public function execute(string $subscriptionId, array $data): array
    {
        return $this->planChangeService->changePlan($subscriptionId, $data);
    }
}
