<?php

namespace App\Jobs;

use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class MarkPastDueOrchestratorJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Tenant::query()
            ->where('status', 'active')
            ->pluck('id')
            ->each(fn (string $tenantId) => MarkPastDueForTenantJob::dispatch($tenantId)->onQueue('billing'));
    }
}
