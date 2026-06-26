<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RegisterTenantAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterTenantRequest;
use App\Http\Resources\AuthTokenResource;
use App\Http\Resources\TenantResource;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Services\Tenancy\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function registerTenant(RegisterTenantRequest $request, RegisterTenantAction $action): JsonResponse
    {
        $result = $action->execute($request->validated());

        return ApiResponse::success([
            'tenant' => new TenantResource($result['tenant']),
            'user' => new UserResource($result['user']),
            'token' => new AuthTokenResource($result['token']),
        ], 201);
    }

    public function login(LoginRequest $request, AuthService $service): JsonResponse
    {
        $result = $service->login($request->validated());

        return ApiResponse::success([
            'user' => new UserResource($result['user']),
            'token' => new AuthTokenResource($result['token']),
        ]);
    }

    public function logout(AuthService $service): JsonResponse
    {
        $service->logout(Auth::user());

        return ApiResponse::success(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('tenant');

        return ApiResponse::success([
            'user' => new UserResource($user),
            'tenant' => new TenantResource($user->tenant),
        ]);
    }
}
