<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'slug',
        'status',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function gracePeriodDays(): int
    {
        return (int) ($this->settings['grace_period_days'] ?? 7);
    }

    public function timezone(): string
    {
        return $this->settings['timezone'] ?? 'UTC';
    }
}
