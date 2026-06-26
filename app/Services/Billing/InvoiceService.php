<?php

namespace App\Services\Billing;

use App\Enums\InvoiceStatus;
use App\Models\InvoiceLineItem;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\TenantSequence;
use App\Services\Tenancy\TenantTimezoneService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(
        private readonly TenantTimezoneService $timezoneService,
    ) {}

    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Invoice::query()->with(['customer', 'subscription']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        return $query->orderByDesc('issued_at')->paginate($perPage);
    }

    public function find(string $id): Invoice
    {
        return Invoice::with(['customer', 'subscription', 'lineItems', 'payments'])->findOrFail($id);
    }

    /**
     * Create invoice for a subscription billing period with idempotency key and allocated invoice number.
     */
    public function createForSubscription(Subscription $subscription): Invoice
    {
        return DB::transaction(function () use ($subscription) {
            $subscription = Subscription::where('id', $subscription->id)->lockForUpdate()->firstOrFail();
            $subscription->load('plan');

            $idempotencyKey = sprintf(
                '%s:%s:%s',
                $subscription->id,
                $subscription->current_period_start->toDateString(),
                $subscription->current_period_end->toDateString(),
            );

            $existing = Invoice::where('billing_idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }

            $issuedAt = CarbonImmutable::now('UTC');
            $dueAt = $this->timezoneService->calculateDueAt($issuedAt);
            $invoiceNumber = $this->allocateInvoiceNumber(TenantContext::id());

            $plan = $subscription->plan;
            $totalCents = $plan->price_cents;

            $invoice = Invoice::create([
                'subscription_id' => $subscription->id,
                'customer_id' => $subscription->customer_id,
                'invoice_number' => $invoiceNumber,
                'status' => InvoiceStatus::Open,
                'subtotal_cents' => $totalCents,
                'tax_cents' => 0,
                'total_cents' => $totalCents,
                'amount_paid_cents' => 0,
                'amount_due_cents' => $totalCents,
                'period_start' => $subscription->current_period_start,
                'period_end' => $subscription->current_period_end,
                'issued_at' => $issuedAt,
                'due_at' => $dueAt,
                'billing_idempotency_key' => $idempotencyKey,
            ]);

            $this->createLineItem($invoice, $plan, $totalCents);

            return $invoice->fresh(['lineItems']);
        });
    }

    public function postInvoiceJournalEntry(Invoice $invoice): Invoice
    {
        $journalService = app(\App\Services\Accounting\JournalEntryService::class);
        $coaService = app(\App\Services\Accounting\ChartOfAccountsService::class);

        $tenantId = $invoice->tenant_id;
        $arAccount = $coaService->findByCode($tenantId, '1100');
        $deferredAccount = $coaService->findByCode($tenantId, '2100');

        $draft = new \App\DTOs\JournalEntryDraft(
            tenantId: $tenantId,
            entryDate: $invoice->issued_at->toDateString(),
            description: "Invoice {$invoice->invoice_number} - subscription billing",
            lines: [
                [
                    'account_id' => $arAccount->id,
                    'debit_cents' => $invoice->total_cents,
                    'credit_cents' => 0,
                    'description' => 'Accounts Receivable',
                ],
                [
                    'account_id' => $deferredAccount->id,
                    'debit_cents' => 0,
                    'credit_cents' => $invoice->total_cents,
                    'description' => 'Deferred Revenue',
                ],
            ],
            idempotencyKey: 'invoice:'.$invoice->id,
        );

        $entry = $journalService->post($draft);
        $invoice->update(['journal_entry_id' => $entry->id]);

        return $invoice->fresh(['journalEntry']);
    }

    public function createRecognitionSchedules(Invoice $invoice): array
    {
        $invoice->load(['subscription.plan']);
        $subscription = $invoice->subscription;
        $plan = $subscription->plan;
        $schedules = [];

        if ($plan->billing_interval->value === 'monthly') {
            $schedules[] = \App\Models\RevenueRecognitionSchedule::create([
                'tenant_id' => $invoice->tenant_id,
                'invoice_id' => $invoice->id,
                'amount_cents' => $invoice->total_cents,
                'period_start' => $invoice->period_start,
                'period_end' => $invoice->period_end,
                'status' => \App\Enums\RecognitionScheduleStatus::Pending,
                'recognition_idempotency_key' => sprintf('%s:%s', $invoice->id, $invoice->period_end->toDateString()),
            ]);
        } else {
            $monthlyAmount = intdiv($invoice->total_cents, 12);
            $remainder = $invoice->total_cents - ($monthlyAmount * 12);
            $periodStart = CarbonImmutable::parse($invoice->period_start->toDateString());

            for ($i = 0; $i < 12; $i++) {
                $scheduleStart = $periodStart->addMonths($i);
                $scheduleEnd = $scheduleStart->addMonth()->subDay();
                $amount = $monthlyAmount + ($i === 11 ? $remainder : 0);

                $schedules[] = \App\Models\RevenueRecognitionSchedule::create([
                    'tenant_id' => $invoice->tenant_id,
                    'invoice_id' => $invoice->id,
                    'amount_cents' => $amount,
                    'period_start' => $scheduleStart->toDateString(),
                    'period_end' => $scheduleEnd->toDateString(),
                    'status' => \App\Enums\RecognitionScheduleStatus::Pending,
                    'recognition_idempotency_key' => sprintf('%s:%s', $invoice->id, $scheduleEnd->toDateString()),
                ]);
            }
        }

        return $schedules;
    }

    private function createLineItem(Invoice $invoice, \App\Models\SubscriptionPlan $plan, int $totalCents): InvoiceLineItem
    {
        return InvoiceLineItem::create([
            'invoice_id' => $invoice->id,
            'description' => sprintf(
                '%s — %s subscription (%s)',
                $plan->name,
                $plan->billing_interval->value,
                $invoice->period_start->toDateString().' to '.$invoice->period_end->toDateString(),
            ),
            'quantity' => 1,
            'unit_price_cents' => $totalCents,
            'amount_cents' => $totalCents,
            'sort_order' => 0,
        ]);
    }

    private function allocateInvoiceNumber(string $tenantId): string
    {
        $sequence = TenantSequence::where('tenant_id', $tenantId)->lockForUpdate()->firstOrFail();
        $number = $sequence->invoice_next_number;
        $sequence->update(['invoice_next_number' => $number + 1]);

        $year = now()->format('Y');

        return sprintf('INV-%s-%05d', $year, $number);
    }
}
