<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'plan_id' => $this->plan_id,
            'price_cents' => $this->price_cents,
            'billing_interval' => $this->billing_interval,
            'status' => $this->status,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'auto_renew' => $this->auto_renew,
            'current_period_start' => $this->current_period_start?->toDateString(),
            'current_period_end' => $this->current_period_end?->toDateString(),
            'cancel_at_period_end' => $this->cancel_at_period_end,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'next_billing_at' => $this->next_billing_at?->toIso8601String(),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'plan' => new PlanResource($this->whenLoaded('plan')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
