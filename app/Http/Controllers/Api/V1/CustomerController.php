<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\CreateCustomerRequest;
use App\Http\Requests\Customers\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Responses\ApiResponse;
use App\Services\Billing\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request, CustomerService $service): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        return ApiResponse::paginated($service->list($perPage), CustomerResource::class);
    }

    public function store(CreateCustomerRequest $request, CustomerService $service): JsonResponse
    {
        return ApiResponse::success(new CustomerResource($service->create($request->validated())), 201);
    }

    public function show(string $customer, CustomerService $service): JsonResponse
    {
        return ApiResponse::success(new CustomerResource($service->find($customer)));
    }

    public function update(UpdateCustomerRequest $request, string $customer, CustomerService $service): JsonResponse
    {
        return ApiResponse::success(
            new CustomerResource($service->update($service->find($customer), $request->validated()))
        );
    }

    public function destroy(string $customer, CustomerService $service): JsonResponse
    {
        return ApiResponse::success(new CustomerResource($service->softDelete($customer)));
    }
}
