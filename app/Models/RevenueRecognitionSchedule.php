<?php

namespace App\Models;

use App\Enums\RecognitionScheduleStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RevenueRecognitionSchedule extends Model
{
    use BelongsToTenant, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'revenue_recognition_schedules';

    protected $fillable = [
        'tenant_id',
        'invoice_id',
        'period_start',
        'period_end',
        'amount_cents',
        'status',
        'journal_entry_id',
        'recognized_at',
        'recognition_idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'status' => RecognitionScheduleStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'recognized_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
