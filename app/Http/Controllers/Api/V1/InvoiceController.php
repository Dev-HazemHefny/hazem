<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Http\Responses\ApiResponse;
use App\Services\Billing\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(Request $request, InvoiceService $service): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        return ApiResponse::paginated(
            $service->list($request->only(['status', 'customer_id']), $perPage),
            InvoiceResource::class,
        );
    }

    public function show(string $invoice, InvoiceService $service): JsonResponse
    {
        return ApiResponse::success(new InvoiceResource($service->find($invoice)));
    }
}
