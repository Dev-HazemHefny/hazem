<?php

namespace App\Actions;

use App\DTOs\JournalEntryDraft;
use App\Models\JournalEntry;
use App\Services\Accounting\JournalEntryService;

class PostJournalEntryAction
{
    public function __construct(
        private readonly JournalEntryService $journalEntryService,
    ) {}

    public function execute(JournalEntryDraft $draft): JournalEntry
    {
        return $this->journalEntryService->post($draft);
    }
}
