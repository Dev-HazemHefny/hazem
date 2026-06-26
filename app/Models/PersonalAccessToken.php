<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'token',
        'abilities',
        'expires_at',
        'tenant_id',
        'tokenable_id',
        'tokenable_type',
    ];
}
