<?php

namespace App\Jobs;

use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunRecognitionOrchestratorJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ?string $periodEnd = null) {}

    public function handle(): void
    {
        Tenant::query()
            ->where('status', 'active')
            ->pluck('id')
            ->each(fn (string $tenantId) => RunRecognitionForTenantJob::dispatch($tenantId, $this->periodEnd)->onQueue('recognition'));
    }
}
