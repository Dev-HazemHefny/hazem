<?php

namespace App\Services\Accounting;

use App\DTOs\JournalEntryDraft;
use App\Enums\JournalEntryStatus;
use App\Exceptions\JournalEntryAlreadyReversedException;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Support\Facades\DB;

class JournalEntryService
{
    public function post(JournalEntryDraft $draft): JournalEntry
    {
        $draft->assertBalanced();

        return DB::transaction(function () use ($draft) {
            if ($draft->idempotencyKey) {
                $existing = JournalEntry::where('tenant_id', $draft->tenantId)
                    ->where('idempotency_key', $draft->idempotencyKey)
                    ->first();

                if ($existing) {
                    return $existing->load('lines');
                }
            }

            $entry = JournalEntry::create([
                'tenant_id' => $draft->tenantId,
                'status' => JournalEntryStatus::Posted,
                'entry_date' => $draft->entryDate,
                'description' => $draft->description,
                'idempotency_key' => $draft->idempotencyKey,
                'posted_at' => now(),
            ]);

            foreach ($draft->lines as $line) {
                JournalLine::create([
                    'tenant_id' => $draft->tenantId,
                    'journal_entry_id' => $entry->id,
                    'account_id' => $line['account_id'],
                    'debit_cents' => $line['debit_cents'] ?? 0,
                    'credit_cents' => $line['credit_cents'] ?? 0,
                    'description' => $line['description'] ?? null,
                ]);
            }

            return $entry->load('lines');
        });
    }

    public function reverse(JournalEntry $original): JournalEntry
    {
        if ($original->status === JournalEntryStatus::Reversed) {
            throw new JournalEntryAlreadyReversedException();
        }

        return DB::transaction(function () use ($original) {
            $original->lockForUpdate();

            if ($original->status === JournalEntryStatus::Reversed) {
                throw new JournalEntryAlreadyReversedException();
            }

            $original->load('lines');

            $reversalLines = $original->lines->map(fn (JournalLine $line) => [
                'account_id' => $line->account_id,
                'debit_cents' => $line->credit_cents,
                'credit_cents' => $line->debit_cents,
                'description' => 'Reversal: '.($line->description ?? ''),
            ])->all();

            $reversalDraft = new JournalEntryDraft(
                tenantId: $original->tenant_id,
                entryDate: now()->toDateString(),
                description: 'Reversal of journal entry '.$original->id,
                lines: $reversalLines,
                idempotencyKey: 'reverse:'.$original->id,
            );

            $reversalEntry = $this->post($reversalDraft);

            $original->update(['status' => JournalEntryStatus::Reversed]);
            $reversalEntry->update(['reverses_entry_id' => $original->id]);

            return $reversalEntry->load('lines');
        });
    }
}
