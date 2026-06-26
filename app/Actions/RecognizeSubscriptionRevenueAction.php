<?php

namespace App\Actions;

use App\Services\Accounting\RevenueRecognitionService;
use Carbon\CarbonImmutable;

class RecognizeSubscriptionRevenueAction
{
    public function __construct(
        private readonly RevenueRecognitionService $revenueRecognitionService,
    ) {}

    public function execute(string|\Carbon\CarbonImmutable|null $periodEnd = null): array
    {
        $runDate = match (true) {
            $periodEnd === null => null,
            $periodEnd instanceof CarbonImmutable => $periodEnd,
            default => CarbonImmutable::parse($periodEnd),
        };

        return $this->revenueRecognitionService->recognizeDueSchedules($runDate);
    }
}
