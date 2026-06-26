<?php

namespace App\Services\Accounting;

use App\Enums\AccountType;
use App\Models\Account;

class ChartOfAccountsService
{
    /** @var array<int, array{code: string, name: string, type: AccountType}> */
    private const DEFAULT_ACCOUNTS = [
        ['code' => '1000', 'name' => 'Cash', 'type' => AccountType::Asset],
        ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => AccountType::Asset],
        ['code' => '2100', 'name' => 'Deferred Revenue', 'type' => AccountType::Liability],
        ['code' => '3000', 'name' => 'Retained Earnings', 'type' => AccountType::Equity],
        ['code' => '4000', 'name' => 'Subscription Revenue', 'type' => AccountType::Revenue],
    ];

    public function seedDefaultAccounts(string $tenantId): void
    {
        foreach (self::DEFAULT_ACCOUNTS as $account) {
            Account::firstOrCreate(
                ['tenant_id' => $tenantId, 'code' => $account['code']],
                [
                    'name' => $account['name'],
                    'type' => $account['type'],
                ],
            );
        }
    }

    public function findByCode(string $tenantId, string $code): Account
    {
        return Account::where('tenant_id', $tenantId)
            ->where('code', $code)
            ->firstOrFail();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Account> */
    public function listAccounts(string $tenantId)
    {
        return Account::where('tenant_id', $tenantId)
            ->orderBy('code')
            ->get();
    }
}
