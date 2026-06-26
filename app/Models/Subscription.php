<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use BelongsToTenant, HasUuids, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'plan_id',
        'price_cents',
        'billing_interval',
        'status',
        'start_date',
        'end_date',
        'auto_renew',
        'current_period_start',
        'current_period_end',
        'cancelled_at',
        'cancel_at_period_end',
        'next_billing_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'current_period_start' => 'date',
            'current_period_end' => 'date',
            'next_billing_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'deleted_at' => 'datetime',
            'auto_renew' => 'boolean',
            'cancel_at_period_end' => 'boolean',
            'price_cents' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
