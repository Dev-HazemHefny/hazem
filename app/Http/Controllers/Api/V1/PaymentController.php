<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RecordPaymentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\RecordPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function store(
        RecordPaymentRequest $request,
        Invoice $invoice,
        RecordPaymentAction $action,
    ): JsonResponse {
        $payment = $action->execute($invoice, $request->validated());

        return ApiResponse::success(new PaymentResource($payment->load(['invoice', 'journalEntry'])), 201);
    }
}
