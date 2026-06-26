<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entry_date' => $this->entry_date?->toDateString(),
            'description' => $this->description,
            'status' => $this->status,
            'reverses_entry_id' => $this->reverses_entry_id,
            'lines' => JournalLineResource::collection($this->whenLoaded('lines')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
