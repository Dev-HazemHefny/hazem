<?php

namespace App\Services\Payment;

use App\DTOs\JournalEntryDraft;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Exceptions\InvalidPaymentAmountException;
use App\Exceptions\InvoiceNotPayableException;
use App\Exceptions\OverpaymentException;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\Accounting\ChartOfAccountsService;
use App\Services\Accounting\JournalEntryService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        private readonly JournalEntryService $journalEntryService,
        private readonly ChartOfAccountsService $chartOfAccountsService,
    ) {}

    /**
     * Record payment with client idempotency key and invoice row lock.
     *
     * @param  array{amount_cents: int, client_idempotency_key: string, payment_method?: string|null}  $data
     */
    public function recordPayment(Invoice $invoice, array $data): Payment
    {
        if ($data['amount_cents'] <= 0) {
            throw new InvalidPaymentAmountException();
        }

        try {
            return DB::transaction(function () use ($invoice, $data) {
                $existing = Payment::where('client_idempotency_key', $data['client_idempotency_key'])
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return $existing->load(['invoice', 'journalEntry']);
                }

                $invoice = Invoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();

                if (in_array($invoice->status, [InvoiceStatus::Paid, InvoiceStatus::Void], true)) {
                    throw new InvoiceNotPayableException($invoice->status->value);
                }

                if ($data['amount_cents'] > $invoice->amount_due_cents) {
                    throw new OverpaymentException($invoice->amount_due_cents, $data['amount_cents']);
                }

                $tenantId = $invoice->tenant_id;
                $cashAccount = $this->chartOfAccountsService->findByCode($tenantId, '1000');
                $arAccount = $this->chartOfAccountsService->findByCode($tenantId, '1100');

                $draft = new JournalEntryDraft(
                    tenantId: $tenantId,
                    entryDate: now()->toDateString(),
                    description: "Payment for invoice {$invoice->invoice_number}",
                    lines: [
                        [
                            'account_id' => $cashAccount->id,
                            'debit_cents' => $data['amount_cents'],
                            'credit_cents' => 0,
                            'description' => 'Cash received',
                        ],
                        [
                            'account_id' => $arAccount->id,
                            'debit_cents' => 0,
                            'credit_cents' => $data['amount_cents'],
                            'description' => 'Accounts Receivable',
                        ],
                    ],
                    idempotencyKey: 'payment:'.$data['client_idempotency_key'],
                );

                $journalEntry = $this->journalEntryService->post($draft);

                $newAmountPaid = $invoice->amount_paid_cents + $data['amount_cents'];
                $newAmountDue = $invoice->total_cents - $newAmountPaid;

                $newStatus = match (true) {
                    $newAmountDue === 0 => InvoiceStatus::Paid,
                    $newAmountPaid > 0 => InvoiceStatus::PartiallyPaid,
                    default => InvoiceStatus::Open,
                };

                $invoice->update([
                    'amount_paid_cents' => $newAmountPaid,
                    'amount_due_cents' => $newAmountDue,
                    'status' => $newStatus,
                ]);

                $payment = Payment::create([
                    'tenant_id' => $tenantId,
                    'invoice_id' => $invoice->id,
                    'customer_id' => $invoice->customer_id,
                    'amount_cents' => $data['amount_cents'],
                    'payment_method' => $data['payment_method'] ?? 'other',
                    'status' => PaymentStatus::Completed,
                    'paid_at' => now(),
                    'client_idempotency_key' => $data['client_idempotency_key'],
                    'journal_entry_id' => $journalEntry->id,
                ]);

                $this->reactivatePastDueSubscriptionIfFullyPaid($invoice);

                return $payment->load(['invoice', 'journalEntry']);
            });
        } catch (UniqueConstraintViolationException) {
            return Payment::where('client_idempotency_key', $data['client_idempotency_key'])
                ->firstOrFail()
                ->load(['invoice', 'journalEntry']);
        }
    }

    private function reactivatePastDueSubscriptionIfFullyPaid(Invoice $invoice): void
    {
        if ($invoice->status !== InvoiceStatus::Paid || ! $invoice->subscription_id) {
            return;
        }

        $subscription = Subscription::where('id', $invoice->subscription_id)->lockForUpdate()->first();

        if (! $subscription || $subscription->status !== SubscriptionStatus::PastDue) {
            return;
        }

        $hasOpenInvoices = Invoice::where('subscription_id', $subscription->id)
            ->whereIn('status', [InvoiceStatus::Open, InvoiceStatus::PartiallyPaid])
            ->exists();

        if (! $hasOpenInvoices) {
            $subscription->update(['status' => SubscriptionStatus::Active]);
        }
    }
}
