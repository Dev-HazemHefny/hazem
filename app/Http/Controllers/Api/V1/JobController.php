<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Jobs\RunRevenueRecognitionRequest;
use App\Http\Responses\ApiResponse;
use App\Jobs\RunBillingOrchestratorJob;
use App\Jobs\RunRecognitionOrchestratorJob;
use Illuminate\Http\JsonResponse;

class JobController extends Controller
{
    public function runBilling(): JsonResponse
    {
        RunBillingOrchestratorJob::dispatch();

        return ApiResponse::success(['message' => 'Billing orchestrator dispatched.']);
    }

    public function runRevenueRecognition(RunRevenueRecognitionRequest $request): JsonResponse
    {
        RunRecognitionOrchestratorJob::dispatch($request->validated()['period_end'] ?? null);

        return ApiResponse::success(['message' => 'Revenue recognition orchestrator dispatched.']);
    }
}
