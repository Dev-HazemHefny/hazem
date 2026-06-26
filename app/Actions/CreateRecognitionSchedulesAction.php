<?php

namespace App\Actions;

use App\Models\Invoice;
use App\Services\Billing\InvoiceService;

class CreateRecognitionSchedulesAction
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
    ) {}

    public function execute(Invoice $invoice): array
    {
        return $this->invoiceService->createRecognitionSchedules($invoice);
    }
}
