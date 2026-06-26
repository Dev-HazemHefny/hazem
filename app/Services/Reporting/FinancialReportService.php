<?php

namespace App\Services\Reporting;

use App\Enums\AccountType;
use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

class FinancialReportService
{
    /**
     * Generate income statement for a date range (tenant-local dates).
     *
     * @return array{from: string, to: string, subscription_revenue_cents: int, net_income_cents: int}
     */
    public function incomeStatement(string $from, string $to): array
    {
        $tenantId = TenantContext::id();
        $revenueAccount = Account::where('tenant_id', $tenantId)->where('code', '4000')->firstOrFail();

        $netRevenue = $this->sumAccountActivity(
            tenantId: $tenantId,
            accountId: $revenueAccount->id,
            from: $from,
            to: $to,
        );

        return [
            'from' => $from,
            'to' => $to,
            'subscription_revenue_cents' => $netRevenue,
            'net_income_cents' => $netRevenue,
        ];
    }

    /**
     * Generate balance sheet as of a tenant-local date.
     */
    public function balanceSheet(string $asOf): array
    {
        $tenantId = TenantContext::id();
        $accounts = Account::where('tenant_id', $tenantId)
            ->whereIn('code', ['1000', '1100', '2100', '3000', '4000'])
            ->get()
            ->keyBy('code');

        $cash = $this->accountBalance($tenantId, $accounts['1000']->id, $asOf, AccountType::Asset);
        $ar = $this->accountBalance($tenantId, $accounts['1100']->id, $asOf, AccountType::Asset);
        $deferred = $this->accountBalance($tenantId, $accounts['2100']->id, $asOf, AccountType::Liability);

        $retainedEarnings = $this->sumAccountActivity(
            tenantId: $tenantId,
            accountId: $accounts['4000']->id,
            asOf: $asOf,
        );

        $totalAssets = $cash + $ar;
        $totalLiabilities = $deferred;
        $totalEquity = $retainedEarnings;
        $balanced = $totalAssets === ($totalLiabilities + $totalEquity);

        return [
            'as_of' => $asOf,
            'assets' => [
                'cash_cents' => $cash,
                'accounts_receivable_cents' => $ar,
                'total_assets_cents' => $totalAssets,
            ],
            'liabilities' => [
                'deferred_revenue_cents' => $deferred,
                'total_liabilities_cents' => $totalLiabilities,
            ],
            'equity' => [
                'retained_earnings_cents' => $retainedEarnings,
                'total_equity_cents' => $totalEquity,
            ],
            'balanced' => $balanced,
        ];
    }

    private function accountBalance(
        string $tenantId,
        string $accountId,
        string $asOf,
        AccountType $type,
    ): int {
        $rawBalance = (int) DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->where('je.tenant_id', $tenantId)
            ->where('je.status', JournalEntryStatus::Posted->value)
            ->where('je.entry_date', '<=', $asOf)
            ->where('jl.account_id', $accountId)
            ->selectRaw('COALESCE(SUM(jl.debit_cents), 0) - COALESCE(SUM(jl.credit_cents), 0) as balance')
            ->value('balance');

        return match ($type) {
            AccountType::Asset => $rawBalance,
            AccountType::Liability, AccountType::Equity, AccountType::Revenue => -$rawBalance,
        };
    }

    private function sumAccountActivity(
        string $tenantId,
        string $accountId,
        ?string $from = null,
        ?string $to = null,
        ?string $asOf = null,
    ): int {
        $query = DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->where('je.tenant_id', $tenantId)
            ->where('je.status', JournalEntryStatus::Posted->value)
            ->where('jl.account_id', $accountId);

        if ($from && $to) {
            $query->whereDate('je.entry_date', '>=', $from)
                ->whereDate('je.entry_date', '<=', $to);
        } elseif ($asOf) {
            $query->where('je.entry_date', '<=', $asOf);
        }

        $credits = (int) (clone $query)->sum('jl.credit_cents');
        $debits = (int) (clone $query)->sum('jl.debit_cents');

        return $credits - $debits;
    }
}
