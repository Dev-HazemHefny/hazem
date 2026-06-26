<?php

namespace App\Exceptions;

class JournalEntryAlreadyReversedException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Journal entry has already been reversed.',
            errorCode: 'JOURNAL_ENTRY_ALREADY_REVERSED',
        );
    }
}
