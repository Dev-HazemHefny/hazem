<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use BelongsToTenant, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'customer_id',
        'invoice_number',
        'status',
        'subtotal_cents',
        'tax_cents',
        'total_cents',
        'amount_paid_cents',
        'amount_due_cents',
        'period_start',
        'period_end',
        'issued_at',
        'due_at',
        'billing_idempotency_key',
        'journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'subtotal_cents' => 'integer',
            'tax_cents' => 'integer',
            'total_cents' => 'integer',
            'amount_paid_cents' => 'integer',
            'amount_due_cents' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
