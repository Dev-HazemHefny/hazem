<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Plans\CreatePlanRequest;
use App\Http\Requests\Plans\UpdatePlanRequest;
use App\Http\Resources\PlanResource;
use App\Http\Responses\ApiResponse;
use App\Services\Billing\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index(Request $request, PlanService $service): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        return ApiResponse::paginated(
            $service->list($request->only(['status']), $perPage),
            PlanResource::class,
        );
    }

    public function store(CreatePlanRequest $request, PlanService $service): JsonResponse
    {
        return ApiResponse::success(new PlanResource($service->create($request->validated())), 201);
    }

    public function show(string $plan, PlanService $service): JsonResponse
    {
        return ApiResponse::success(new PlanResource($service->find($plan)));
    }

    public function update(UpdatePlanRequest $request, string $plan, PlanService $service): JsonResponse
    {
        return ApiResponse::success(
            new PlanResource($service->update($service->find($plan), $request->validated()))
        );
    }

    public function destroy(string $plan, PlanService $service): JsonResponse
    {
        return ApiResponse::success(new PlanResource($service->deactivate($plan)));
    }
}
