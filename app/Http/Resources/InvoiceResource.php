<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'customer_id' => $this->customer_id,
            'subscription_id' => $this->subscription_id,
            'status' => $this->status,
            'subtotal_cents' => $this->subtotal_cents,
            'tax_cents' => $this->tax_cents,
            'total_cents' => $this->total_cents,
            'amount_paid_cents' => $this->amount_paid_cents,
            'amount_due_cents' => $this->amount_due_cents,
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'issued_at' => $this->issued_at?->toIso8601String(),
            'due_at' => $this->due_at?->toIso8601String(),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'subscription' => new SubscriptionResource($this->whenLoaded('subscription')),
            'line_items' => InvoiceLineItemResource::collection($this->whenLoaded('lineItems')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
