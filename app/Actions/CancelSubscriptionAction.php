<?php

namespace App\Actions;

use App\Models\Subscription;
use App\Services\Billing\SubscriptionService;

class CancelSubscriptionAction
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
    ) {}

    public function execute(string $subscriptionId, bool $cancelAtPeriodEnd = true): Subscription
    {
        return $this->subscriptionService->cancel($subscriptionId, $cancelAtPeriodEnd);
    }
}
