<?php

namespace App\Jobs;

use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunBillingOrchestratorJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Tenant::query()
            ->where('status', 'active')
            ->pluck('id')
            ->each(fn (string $tenantId) => RunBillingForTenantJob::dispatch($tenantId)->onQueue('billing'));
    }
}
