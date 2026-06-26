<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncomeStatementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'from' => $this->resource['from'],
            'to' => $this->resource['to'],
            'subscription_revenue_cents' => $this->resource['subscription_revenue_cents'],
            'net_income_cents' => $this->resource['net_income_cents'],
        ];
    }
}
