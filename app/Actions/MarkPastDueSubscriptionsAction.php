<?php

namespace App\Actions;

use App\Services\Subscription\PastDueService;
use Carbon\CarbonImmutable;

class MarkPastDueSubscriptionsAction
{
    public function __construct(
        private readonly PastDueService $pastDueService,
    ) {}

    public function execute(?CarbonImmutable $asOf = null): array
    {
        return $this->pastDueService->markPastDueSubscriptions($asOf);
    }
}
