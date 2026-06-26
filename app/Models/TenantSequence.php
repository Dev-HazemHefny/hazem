<?php

namespace App\Models;

use App\Models\Concerns\AppliesTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSequence extends Model
{
    use AppliesTenantScope;

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'tenant_id';

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'invoice_next_number',
    ];

    protected static function booted(): void
    {
        static::applyTenantGlobalScope();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
