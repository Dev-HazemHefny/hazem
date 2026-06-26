<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceLineItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price_cents' => $this->unit_price_cents,
            'amount_cents' => $this->amount_cents,
            'total_cents' => $this->amount_cents,
        ];
    }
}
