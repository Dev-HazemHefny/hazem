<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'customer_id' => $this->customer_id,
            'amount_cents' => $this->amount_cents,
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'client_idempotency_key' => $this->client_idempotency_key,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
