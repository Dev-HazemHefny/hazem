<?php

namespace App\Actions;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Payment\PaymentService;

class RecordPaymentAction
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    /**
     * @param  array{amount_cents: int, client_idempotency_key: string, payment_method?: string|null}  $data
     */
    public function execute(Invoice $invoice, array $data): Payment
    {
        return $this->paymentService->recordPayment($invoice, $data);
    }
}
