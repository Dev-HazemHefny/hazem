<?php

namespace App\Jobs;

use App\Actions\MarkPastDueSubscriptionsAction;
use App\Support\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class MarkPastDueForTenantJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly string $tenantId) {}

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(MarkPastDueSubscriptionsAction $action): void
    {
        try {
            TenantContext::runAs($this->tenantId, fn () => $action->execute());
        } catch (Throwable $e) {
            Log::error('Mark past due failed for tenant', [
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
