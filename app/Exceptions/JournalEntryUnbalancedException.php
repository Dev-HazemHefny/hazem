<?php

namespace App\Exceptions;

class JournalEntryUnbalancedException extends DomainException
{
    public function __construct(int $totalDebits, int $totalCredits)
    {
        parent::__construct(
            message: 'Journal entry is not balanced.',
            errorCode: 'JOURNAL_ENTRY_UNBALANCED',
            details: [
                'total_debits_cents' => $totalDebits,
                'total_credits_cents' => $totalCredits,
            ],
        );
    }
}
