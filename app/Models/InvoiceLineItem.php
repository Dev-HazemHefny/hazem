<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLineItem extends Model
{
    use BelongsToTenant, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'invoice_id',
        'description',
        'quantity',
        'unit_price_cents',
        'amount_cents',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_cents' => 'integer',
            'amount_cents' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
