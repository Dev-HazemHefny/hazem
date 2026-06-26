<?php

namespace App\Actions;

use App\Models\JournalEntry;
use App\Services\Accounting\JournalEntryService;

class ReverseJournalEntryAction
{
    public function __construct(
        private readonly JournalEntryService $journalEntryService,
    ) {}

    public function execute(JournalEntry $original): JournalEntry
    {
        return $this->journalEntryService->reverse($original);
    }
}
