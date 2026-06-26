<?php

namespace App\Actions;

use App\Services\Accounting\RevenueRecognitionService;
use Carbon\CarbonImmutable;

class RecognizeSubscriptionRevenueAction
{
    public function __construct(
        private readonly RevenueRecognitionService $revenueRecognitionService,
    ) {}

    public function execute(?CarbonImmutable $periodEnd = null): array
    {
        return $this->revenueRecognitionService->recognizeDueSchedules($periodEnd);
    }
}
