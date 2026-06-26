<?php

namespace App\Exceptions;

class PostedJournalEntryImmutableException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Posted journal entries cannot be modified. Use reversal instead.',
            errorCode: 'JOURNAL_ENTRY_IMMUTABLE',
            httpStatus: 422,
        );
    }
}
