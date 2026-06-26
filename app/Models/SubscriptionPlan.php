<?php

namespace App\Models;

use App\Enums\BillingInterval;
use App\Enums\PlanStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use BelongsToTenant, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'subscription_plans';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'price_cents',
        'currency',
        'billing_interval',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'status' => PlanStatus::class,
            'billing_interval' => BillingInterval::class,
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }
}
