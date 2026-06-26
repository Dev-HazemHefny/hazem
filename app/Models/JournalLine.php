<?php

namespace App\Models;

use App\Models\Concerns\AppliesTenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalLine extends Model
{
    use AppliesTenantScope, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'journal_entry_id',
        'account_id',
        'debit_cents',
        'credit_cents',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'debit_cents' => 'integer',
            'credit_cents' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::applyTenantGlobalScope();
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
