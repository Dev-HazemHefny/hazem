<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AccountResource;
use App\Http\Responses\ApiResponse;
use App\Services\Accounting\AccountQueryService;
use Illuminate\Http\JsonResponse;

class AccountController extends Controller
{
    public function index(AccountQueryService $service): JsonResponse
    {
        return ApiResponse::success(AccountResource::collection($service->list()));
    }
}
