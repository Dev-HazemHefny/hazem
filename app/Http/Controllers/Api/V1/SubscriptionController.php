<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ChangeSubscriptionPlanAction;
use App\Models\Subscription;
use App\Http\Controllers\Controller;
use App\Http\Requests\Subscriptions\CancelSubscriptionRequest;
use App\Http\Requests\Subscriptions\ChangeSubscriptionPlanRequest;
use App\Http\Requests\Subscriptions\CreateSubscriptionRequest;
use App\Http\Requests\Subscriptions\UpdateSubscriptionRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\SubscriptionResource;
use App\Http\Responses\ApiResponse;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index(Request $request, SubscriptionService $service): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        return ApiResponse::paginated(
            $service->list($request->only(['status', 'customer_id']), $perPage),
            SubscriptionResource::class,
        );
    }

    public function store(CreateSubscriptionRequest $request, SubscriptionService $service): JsonResponse
    {
        return ApiResponse::success(
            new SubscriptionResource($service->create($request->validated())->load(['customer', 'plan'])),
            201,
        );
    }

    public function show(string $subscription, SubscriptionService $service): JsonResponse
    {
        return ApiResponse::success(new SubscriptionResource($service->find($subscription)));
    }

    public function update(UpdateSubscriptionRequest $request, string $subscription, SubscriptionService $service): JsonResponse
    {
        return ApiResponse::success(
            new SubscriptionResource($service->update($service->find($subscription), $request->validated()))
        );
    }

    public function cancel(CancelSubscriptionRequest $request, Subscription $subscription, SubscriptionService $service): JsonResponse
    {
        $cancelAtPeriodEnd = $request->validated()['cancel_at_period_end'] ?? true;

        return ApiResponse::success(
            new SubscriptionResource($service->cancel($subscription->id, $cancelAtPeriodEnd))
        );
    }

    public function destroy(Subscription $subscription, SubscriptionService $service): JsonResponse
    {
        return ApiResponse::success(
            new SubscriptionResource($service->delete($subscription->id))
        );
    }

    public function changePlan(
        ChangeSubscriptionPlanRequest $request,
        Subscription $subscription,
        ChangeSubscriptionPlanAction $action,
    ): JsonResponse {
        $result = $action->execute($subscription->id, $request->validated());

        return ApiResponse::success([
            'subscription' => new SubscriptionResource($result['subscription']),
            'proration' => $result['proration'],
            'proration_invoice' => $result['proration_invoice']
                ? new InvoiceResource($result['proration_invoice'])
                : null,
        ]);
    }
}
