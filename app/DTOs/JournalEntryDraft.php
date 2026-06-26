<?php

namespace App\DTOs;

use App\Exceptions\JournalEntryUnbalancedException;

final class JournalEntryDraft
{
    /** @param  array<int, array{account_id: string, debit_cents?: int, credit_cents?: int, description?: string|null}>  $lines */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $entryDate,
        public readonly string $description,
        public readonly array $lines,
        public readonly ?string $idempotencyKey = null,
    ) {}

    public function isBalanced(): bool
    {
        return $this->totalDebits() === $this->totalCredits();
    }

    public function totalDebits(): int
    {
        return array_reduce($this->lines, fn (int $sum, array $line) => $sum + ($line['debit_cents'] ?? 0), 0);
    }

    public function totalCredits(): int
    {
        return array_reduce($this->lines, fn (int $sum, array $line) => $sum + ($line['credit_cents'] ?? 0), 0);
    }

    public function assertBalanced(): void
    {
        if (! $this->isBalanced()) {
            throw new JournalEntryUnbalancedException($this->totalDebits(), $this->totalCredits());
        }
    }
}
