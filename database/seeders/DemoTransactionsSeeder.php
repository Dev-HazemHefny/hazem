<?php

namespace Database\Seeders;

use App\Actions\MarkPastDueSubscriptionsAction;
use App\Actions\RecognizeSubscriptionRevenueAction;
use App\Actions\RecordPaymentAction;
use App\Actions\RunBillingCycleAction;
use App\Models\Invoice;
use App\Support\TenantContext;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DemoTransactionsSeeder extends Seeder
{
    public function run(
        RunBillingCycleAction $billingAction,
        RecordPaymentAction $paymentAction,
        RecognizeSubscriptionRevenueAction $recognitionAction,
        MarkPastDueSubscriptionsAction $markPastDueAction,
    ): void {
        $tenantId = DemoTenantSeeder::$tenantId;

        if (! $tenantId) {
            return;
        }

        TenantContext::runAs($tenantId, function () use (
            $billingAction,
            $paymentAction,
            $recognitionAction,
            $markPastDueAction,
        ) {
            $periodEnd = Carbon::now('America/New_York')->startOfMonth()->subDay()->toDateString();

            $billingAction->execute();

            $aliceInvoice = Invoice::where('subscription_id', DemoSubscriptionsSeeder::$subscriptions['Alice'] ?? null)->first();
            $carolInvoice = Invoice::where('subscription_id', DemoSubscriptionsSeeder::$subscriptions['Carol'] ?? null)->first();

            if ($aliceInvoice) {
                $paymentAction->execute($aliceInvoice, [
                    'amount_cents' => $aliceInvoice->total_cents,
                    'client_idempotency_key' => 'seed-pay-alice-1',
                    'payment_method' => 'card',
                ]);
            }

            if ($carolInvoice) {
                $paymentAction->execute($carolInvoice, [
                    'amount_cents' => 25000,
                    'client_idempotency_key' => 'seed-pay-carol-1',
                    'payment_method' => 'card',
                ]);
            }

            $recognitionAction->execute(Carbon::parse($periodEnd)->toImmutable());
            $markPastDueAction->execute();
        });
    }
}
