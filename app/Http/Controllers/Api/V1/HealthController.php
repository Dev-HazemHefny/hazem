<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\System\HealthService;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(HealthService $service): JsonResponse
    {
        $result = $service->check();

        return ApiResponse::success($result, $service->httpStatus($result));
    }
}
