<?php

namespace Tests\Unit;

use App\DTOs\JournalEntryDraft;
use App\Exceptions\JournalEntryUnbalancedException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JournalEntryDraftTest extends TestCase
{
    #[Test]
    public function balanced_draft_passes_assertion(): void
    {
        $draft = new JournalEntryDraft(
            tenantId: '00000000-0000-0000-0000-000000000001',
            entryDate: '2025-01-01',
            description: 'Test entry',
            lines: [
                ['account_id' => 'a1', 'debit_cents' => 10000, 'credit_cents' => 0],
                ['account_id' => 'a2', 'debit_cents' => 0, 'credit_cents' => 10000],
            ],
        );

        $this->assertTrue($draft->isBalanced());
        $draft->assertBalanced();
    }

    #[Test]
    public function unbalanced_draft_throws_exception(): void
    {
        $draft = new JournalEntryDraft(
            tenantId: '00000000-0000-0000-0000-000000000001',
            entryDate: '2025-01-01',
            description: 'Bad entry',
            lines: [
                ['account_id' => 'a1', 'debit_cents' => 10000, 'credit_cents' => 0],
                ['account_id' => 'a2', 'debit_cents' => 0, 'credit_cents' => 5000],
            ],
        );

        $this->assertFalse($draft->isBalanced());

        $this->expectException(JournalEntryUnbalancedException::class);
        $draft->assertBalanced();
    }
}
