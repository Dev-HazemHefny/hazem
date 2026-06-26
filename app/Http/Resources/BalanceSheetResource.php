<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BalanceSheetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'as_of' => $this->resource['as_of'],
            'assets' => $this->resource['assets'],
            'liabilities' => $this->resource['liabilities'],
            'equity' => $this->resource['equity'],
            'balanced' => $this->resource['balanced'],
        ];
    }
}
