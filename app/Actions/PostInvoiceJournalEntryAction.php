<?php

namespace App\Actions;

use App\Models\Invoice;
use App\Services\Billing\InvoiceService;

class PostInvoiceJournalEntryAction
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
    ) {}

    public function execute(Invoice $invoice): Invoice
    {
        return $this->invoiceService->postInvoiceJournalEntry($invoice);
    }
}
