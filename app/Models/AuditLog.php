<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use BelongsToTenant, HasUuids;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'payload',
        'ip_address',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
