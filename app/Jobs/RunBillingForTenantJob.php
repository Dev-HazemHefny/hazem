<?php

namespace App\Jobs;

use App\Actions\RunBillingCycleAction;
use App\Support\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunBillingForTenantJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly string $tenantId) {}

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(RunBillingCycleAction $action): void
    {
        try {
            TenantContext::runAs($this->tenantId, fn () => $action->execute());
        } catch (Throwable $e) {
            Log::error('Billing failed for tenant', [
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            if (! app()->environment('testing')) {
                DB::disconnect();
            }
        }
    }
}
