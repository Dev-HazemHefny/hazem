<?php

namespace App\Actions;

use App\Models\Tenant;
use App\Services\Accounting\ChartOfAccountsService;

class SeedDefaultChartOfAccountsAction
{
    public function __construct(
        private readonly ChartOfAccountsService $chartOfAccountsService,
    ) {}

    public function execute(Tenant|string $tenant): void
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;
        $this->chartOfAccountsService->seedDefaultAccounts($tenantId);
    }
}
