<?php

namespace App\Services\Accounting;

use App\Models\JournalEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class JournalEntryQueryService
{
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = JournalEntry::query()->with('lines.account');

        if (! empty($filters['from'])) {
            $query->where('entry_date', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('entry_date', '<=', $filters['to']);
        }

        return $query->orderByDesc('entry_date')->paginate($perPage);
    }
}
