<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'account' => new AccountResource($this->whenLoaded('account')),
            'debit_cents' => $this->debit_cents,
            'credit_cents' => $this->credit_cents,
            'description' => $this->description,
        ];
    }
}
