<?php

namespace App\Jobs;

use App\Actions\RecognizeSubscriptionRevenueAction;
use App\Support\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunRecognitionForTenantJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $tenantId,
        public readonly ?string $periodEnd = null,
    ) {}

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(RecognizeSubscriptionRevenueAction $action): void
    {
        try {
            TenantContext::runAs($this->tenantId, fn () => $action->execute($this->periodEnd));
        } catch (Throwable $e) {
            Log::error('Revenue recognition failed for tenant', [
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            DB::disconnect();
        }
    }
}
