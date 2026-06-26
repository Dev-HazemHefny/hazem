<?php

namespace Tests\Feature\Accounting;

use App\Enums\JournalEntryStatus;
use App\Exceptions\PostedJournalEntryImmutableException;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Support\TenantContext;
use Tests\Concerns\InteractsWithTenantApi;
use Tests\Concerns\RefreshDatabaseForTesting;
use Tests\TestCase;

class JournalEntryImmutabilityTest extends TestCase
{
    use InteractsWithTenantApi, RefreshDatabaseForTesting;

    public function test_posted_journal_entry_cannot_be_updated(): void
    {
        $data = $this->registerTenant();
        $tenantId = $data['tenant']['id'];

        TenantContext::runAs($tenantId, function () use ($tenantId) {
            $account = Account::where('code', '1000')->first();

            $entry = JournalEntry::create([
                'tenant_id' => $tenantId,
                'status' => JournalEntryStatus::Posted,
                'entry_date' => '2025-01-01',
                'description' => 'Immutable test',
                'posted_at' => now(),
            ]);

            JournalLine::create([
                'tenant_id' => $tenantId,
                'journal_entry_id' => $entry->id,
                'account_id' => $account->id,
                'debit_cents' => 1000,
                'credit_cents' => 0,
            ]);

            $this->expectException(PostedJournalEntryImmutableException::class);
            $entry->update(['description' => 'Changed']);
        });
    }

    public function test_posted_journal_entry_cannot_be_deleted(): void
    {
        $data = $this->registerTenant();
        $tenantId = $data['tenant']['id'];

        TenantContext::runAs($tenantId, function () use ($tenantId) {
            $entry = JournalEntry::create([
                'tenant_id' => $tenantId,
                'status' => JournalEntryStatus::Posted,
                'entry_date' => '2025-01-01',
                'description' => 'Delete test',
                'posted_at' => now(),
            ]);

            $this->expectException(PostedJournalEntryImmutableException::class);
            $entry->delete();
        });
    }
}
