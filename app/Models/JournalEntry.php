<?php

namespace App\Models;

use App\Enums\JournalEntryStatus;
use App\Exceptions\PostedJournalEntryImmutableException;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    use BelongsToTenant, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'entry_date',
        'description',
        'status',
        'reverses_entry_id',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'status' => JournalEntryStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $entry): void {
            $originalStatus = $entry->getOriginal('status');
            $wasPosted = $originalStatus instanceof JournalEntryStatus
                ? $originalStatus === JournalEntryStatus::Posted
                : $originalStatus === JournalEntryStatus::Posted->value;

            if ($wasPosted && $entry->isDirty() && ! $entry->isDirty('status')) {
                throw new PostedJournalEntryImmutableException();
            }
        });

        static::deleting(function (self $entry): void {
            if ($entry->status === JournalEntryStatus::Posted) {
                throw new PostedJournalEntryImmutableException();
            }
        });
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }
}
