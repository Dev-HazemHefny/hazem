<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TenantSignupEvent extends Model
{
    use BelongsToTenant, HasUuids;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'event_type',
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
}
