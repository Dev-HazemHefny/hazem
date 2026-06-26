<?php

namespace App\Actions;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Services\Billing\InvoiceService;

class CreateInvoiceAction
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
    ) {}

    public function execute(Subscription $subscription): Invoice
    {
        return $this->invoiceService->createForSubscription($subscription);
    }
}
