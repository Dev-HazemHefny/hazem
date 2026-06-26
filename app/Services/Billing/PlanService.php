<?php

namespace App\Services\Billing;

use App\Enums\PlanStatus;
use App\Enums\SubscriptionStatus;
use App\Exceptions\PlanHasActiveSubscriptionsException;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PlanService
{
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = SubscriptionPlan::query();

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    public function create(array $data): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price_cents' => $data['price_cents'],
            'currency' => $data['currency'] ?? 'USD',
            'billing_interval' => $data['billing_interval'],
            'status' => PlanStatus::Active,
        ]);
    }

    public function find(string $id): SubscriptionPlan
    {
        return SubscriptionPlan::findOrFail($id);
    }

    public function update(SubscriptionPlan $plan, array $data): SubscriptionPlan
    {
        $plan->update(array_filter([
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
            'price_cents' => $data['price_cents'] ?? null,
            'currency' => $data['currency'] ?? null,
            'billing_interval' => $data['billing_interval'] ?? null,
        ], fn ($v) => $v !== null));

        return $plan->fresh();
    }

    public function deactivate(string $planId): SubscriptionPlan
    {
        return DB::transaction(function () use ($planId) {
            $plan = SubscriptionPlan::where('id', $planId)->sharedLock()->firstOrFail();

            $hasActiveSubscriptions = Subscription::where('plan_id', $plan->id)
                ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue])
                ->exists();

            if ($hasActiveSubscriptions) {
                throw new PlanHasActiveSubscriptionsException();
            }

            $plan->update(['status' => PlanStatus::Inactive]);

            return $plan->fresh();
        });
    }
}
